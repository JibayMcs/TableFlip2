/**
 * BookmarkStore — localStorage-backed list of saved database connections,
 * encrypted with a user-supplied master password.
 *
 * Schema (version 2)
 *   - Only `label` and `color` stay in clear so the navbar switcher can
 *     display something useful while the store is locked.
 *   - Driver, host, port, database, username and password are bundled
 *     into a single JSON blob and encrypted together.
 *
 * Crypto
 *   - PBKDF2 (200_000 iterations, SHA-256) derives an AES-GCM 256-bit key
 *     from the master password + a per-installation salt.
 *   - Each entry has its own 12-byte IV.
 *   - A verification token is encrypted on first init to detect a wrong
 *     master password at unlock time without try-decrypting every entry.
 *
 * Memory
 *   - The master password never leaves browser memory : it lives in `#key`
 *     until `lock()` is called. It is never written to localStorage,
 *     sessionStorage, IndexedDB, or sent to the server.
 */

const STORAGE_VERSION = 2;
const STORAGE_KEY = 'tableflip:bookmarks:v2';
const SESSION_KEY = 'tableflip:bookmarks:session-key';
const PBKDF2_ITERATIONS = 200_000;
const VERIFICATION_PLAINTEXT = 'tableflip-bookmark-verify-v1';

/** Palette keys used for the per-bookmark accent. View layer maps them to colours. */
export const BOOKMARK_COLORS = [
    'zinc', 'blue', 'emerald', 'amber', 'rose', 'violet', 'cyan', 'pink',
] as const;
export type BookmarkColor = (typeof BOOKMARK_COLORS)[number];
export const DEFAULT_BOOKMARK_COLOR: BookmarkColor = 'zinc';

/** Public face of a bookmark : visible even when the store is locked. */
export interface Bookmark {
    id: string;
    label: string;
    color: BookmarkColor;
}

/** Full bookmark — requires the store to be unlocked. */
export interface DecryptedBookmark extends Bookmark {
    driver: string;
    host: string;
    port: number | null;
    database: string;
    username: string;
    password: string;
}

interface StoredEntry {
    id: string;
    label: string;
    color: BookmarkColor;
    iv: string;          // base64
    ciphertext: string;  // base64 — encrypts SecretPayload
}

interface SecretPayload {
    driver: string;
    host: string;
    port: number | null;
    database: string;
    username: string;
    password: string;
}

interface StoredPayload {
    version: number;
    salt: string;             // base64 — per-installation, used by PBKDF2
    verifyIv: string;         // base64
    verifyCiphertext: string; // base64 — encrypted VERIFICATION_PLAINTEXT
    entries: StoredEntry[];
}

function b64encode(bytes: ArrayBuffer | Uint8Array): string {
    const arr = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    let binary = '';
    for (const byte of arr) {
        binary += String.fromCharCode(byte);
    }
    return btoa(binary);
}

function b64decode(input: string): Uint8Array {
    const binary = atob(input);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
}

function randomBytes(length: number): Uint8Array {
    return crypto.getRandomValues(new Uint8Array(length));
}

function loadPayload(): StoredPayload | null {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw === null) {
        // Wipe any stale v1 leftover so old encrypted entries don't accumulate.
        localStorage.removeItem('tableflip:bookmarks:v1');
        return null;
    }
    try {
        const parsed = JSON.parse(raw) as StoredPayload;
        if (parsed.version !== STORAGE_VERSION) {
            return null;
        }
        return parsed;
    } catch {
        return null;
    }
}

function savePayload(payload: StoredPayload): void {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
}

function sanitiseColor(input: string | undefined): BookmarkColor {
    return (BOOKMARK_COLORS as readonly string[]).includes(input ?? '')
        ? input as BookmarkColor
        : DEFAULT_BOOKMARK_COLOR;
}

export class BookmarkStore {
    /** AES-GCM key derived from the master password. Null when locked. */
    #key: CryptoKey | null = null;

    /** Cache of the loaded payload to avoid repeat parsing. */
    #payload: StoredPayload | null;

    constructor() {
        this.#payload = loadPayload();
    }

    hasBookmarks(): boolean {
        return this.#payload !== null && this.#payload.entries.length > 0;
    }

    isInitialised(): boolean {
        return this.#payload !== null;
    }

    isUnlocked(): boolean {
        return this.#key !== null;
    }

    lock(): void {
        this.#key = null;
        try { sessionStorage.removeItem(SESSION_KEY); } catch { /* ignored */ }
    }

    /**
     * Try to restore the derived key from sessionStorage. Returns true when
     * the cached key matches the current verification token. Use this on
     * page load so the master password survives navigations within the
     * same tab without weakening the at-rest threat model — sessionStorage
     * is cleared when the tab closes.
     */
    async tryRestoreFromSession(): Promise<boolean> {
        if (this.#payload === null) {
            return false;
        }
        let raw: string | null;
        try {
            raw = sessionStorage.getItem(SESSION_KEY);
        } catch {
            return false;
        }
        if (raw === null) {
            return false;
        }
        try {
            const jwk = JSON.parse(raw) as JsonWebKey;
            const key = await crypto.subtle.importKey(
                'jwk',
                jwk,
                { name: 'AES-GCM', length: 256 },
                true,
                ['encrypt', 'decrypt'],
            );
            const iv = b64decode(this.#payload.verifyIv);
            const ciphertext = b64decode(this.#payload.verifyCiphertext);
            const plain = await this.decryptString(key, iv, ciphertext);
            if (plain !== VERIFICATION_PLAINTEXT) {
                this.clearSessionKey();
                return false;
            }
            this.#key = key;
            return true;
        } catch {
            this.clearSessionKey();
            return false;
        }
    }

    /**
     * Sets the master password for the first time. Fails if a payload
     * already exists — use changeMaster() to rotate.
     */
    async initialise(masterPassword: string): Promise<void> {
        if (this.#payload !== null) {
            throw new Error('Bookmarks already initialised.');
        }

        const salt = randomBytes(16);
        const key = await this.deriveKey(masterPassword, salt);
        const verifyIv = randomBytes(12);
        const verifyCiphertext = await this.encryptString(key, verifyIv, VERIFICATION_PLAINTEXT);

        this.#payload = {
            version: STORAGE_VERSION,
            salt: b64encode(salt),
            verifyIv: b64encode(verifyIv),
            verifyCiphertext: b64encode(verifyCiphertext),
            entries: [],
        };
        this.#key = key;
        savePayload(this.#payload);
        await this.cacheSessionKey(key);
    }

    /** True on success, false when the master password is wrong. */
    async unlock(masterPassword: string): Promise<boolean> {
        if (this.#payload === null) {
            return false;
        }

        const salt = b64decode(this.#payload.salt);
        const key = await this.deriveKey(masterPassword, salt);

        const iv = b64decode(this.#payload.verifyIv);
        const ciphertext = b64decode(this.#payload.verifyCiphertext);
        try {
            const plain = await this.decryptString(key, iv, ciphertext);
            if (plain !== VERIFICATION_PLAINTEXT) {
                return false;
            }
        } catch {
            return false;
        }

        this.#key = key;
        await this.cacheSessionKey(key);
        return true;
    }

    /** Decrypted list — requires unlock. */
    async list(): Promise<DecryptedBookmark[]> {
        if (this.#key === null || this.#payload === null) {
            return [];
        }

        const key = this.#key;
        const result: DecryptedBookmark[] = [];
        for (const entry of this.#payload.entries) {
            try {
                const secret = await this.decryptSecret(key, entry);
                result.push({
                    id: entry.id,
                    label: entry.label,
                    color: sanitiseColor(entry.color),
                    ...secret,
                });
            } catch {
                // Skip silently — a corrupted entry shouldn't break the list.
            }
        }
        return result;
    }

    async get(id: string): Promise<DecryptedBookmark | null> {
        const all = await this.list();
        return all.find(b => b.id === id) ?? null;
    }

    /**
     * Adds (or replaces) a bookmark. When an `id` is given the entry is
     * updated in place ; otherwise a new id is generated.
     */
    async upsert(bookmark: Omit<DecryptedBookmark, 'id'> & { id?: string }): Promise<string> {
        if (this.#key === null || this.#payload === null) {
            throw new Error('Store is locked.');
        }

        const id = bookmark.id ?? crypto.randomUUID();
        const iv = randomBytes(12);
        const secret: SecretPayload = {
            driver: bookmark.driver,
            host: bookmark.host,
            port: bookmark.port,
            database: bookmark.database,
            username: bookmark.username,
            password: bookmark.password,
        };
        const ciphertext = await this.encryptString(this.#key, iv, JSON.stringify(secret));

        const entry: StoredEntry = {
            id,
            label: bookmark.label,
            color: sanitiseColor(bookmark.color),
            iv: b64encode(iv),
            ciphertext: b64encode(ciphertext),
        };

        const existingIndex = this.#payload.entries.findIndex(e => e.id === id);
        if (existingIndex === -1) {
            this.#payload.entries.push(entry);
        } else {
            this.#payload.entries[existingIndex] = entry;
        }
        savePayload(this.#payload);
        return id;
    }

    remove(id: string): void {
        if (this.#payload === null) {
            return;
        }
        this.#payload.entries = this.#payload.entries.filter(e => e.id !== id);
        savePayload(this.#payload);
    }

    wipe(): void {
        localStorage.removeItem(STORAGE_KEY);
        this.clearSessionKey();
        this.#payload = null;
        this.#key = null;
    }

    private clearSessionKey(): void {
        try { sessionStorage.removeItem(SESSION_KEY); } catch { /* ignored */ }
    }

    private async cacheSessionKey(key: CryptoKey): Promise<void> {
        try {
            const jwk = await crypto.subtle.exportKey('jwk', key);
            sessionStorage.setItem(SESSION_KEY, JSON.stringify(jwk));
        } catch {
            // Non-extractable key or storage failure — silently skip.
        }
    }

    /**
     * Public-only list — visible while locked. Holds id, label and color
     * so the navbar switch dropdown can display useful entries without
     * needing the master password.
     */
    listPublic(): Bookmark[] {
        if (this.#payload === null) {
            return [];
        }
        return this.#payload.entries.map(e => ({
            id: e.id,
            label: e.label,
            color: sanitiseColor(e.color),
        }));
    }

    private async decryptSecret(key: CryptoKey, entry: StoredEntry): Promise<SecretPayload> {
        const plain = await this.decryptString(
            key,
            b64decode(entry.iv),
            b64decode(entry.ciphertext),
        );
        return JSON.parse(plain) as SecretPayload;
    }

    private async deriveKey(password: string, salt: Uint8Array): Promise<CryptoKey> {
        const material = await crypto.subtle.importKey(
            'raw',
            new TextEncoder().encode(password),
            'PBKDF2',
            false,
            ['deriveKey'],
        );
        return crypto.subtle.deriveKey(
            { name: 'PBKDF2', salt, iterations: PBKDF2_ITERATIONS, hash: 'SHA-256' },
            material,
            { name: 'AES-GCM', length: 256 },
            true, // extractable — required to cache as JWK in sessionStorage
            ['encrypt', 'decrypt'],
        );
    }

    private async encryptString(key: CryptoKey, iv: Uint8Array, value: string): Promise<ArrayBuffer> {
        return crypto.subtle.encrypt(
            { name: 'AES-GCM', iv },
            key,
            new TextEncoder().encode(value),
        );
    }

    private async decryptString(key: CryptoKey, iv: Uint8Array, ciphertext: Uint8Array): Promise<string> {
        const plain = await crypto.subtle.decrypt(
            { name: 'AES-GCM', iv },
            key,
            ciphertext,
        );
        return new TextDecoder().decode(plain);
    }
}

/** Global singleton — shared between login page and navbar dropdown. */
let instance: BookmarkStore | null = null;

export function getBookmarkStore(): BookmarkStore {
    if (instance === null) {
        instance = new BookmarkStore();
    }
    return instance;
}
