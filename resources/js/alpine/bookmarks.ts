/**
 * Alpine plugin : `x-bookmarks` directive that exposes a reactive
 * `$store.bookmarks` helper to the surrounding Alpine scope.
 *
 * Usage in Blade :
 *   <div x-data x-bookmarks>
 *       <template x-for="b in bookmarks.list">
 *           <button @click="bookmarks.fill(b.id)" ...>
 *               <span x-text="b.label"></span>
 *           </button>
 *       </template>
 *   </div>
 *
 * The directive injects a `bookmarks` reactive object into the closest
 * Alpine scope. Methods are async (the underlying crypto is) ; the UI
 * binds against state mutated synchronously at the end of each call.
 */

import type { AlpineLike } from '../types';
import {
    BOOKMARK_COLORS,
    DEFAULT_BOOKMARK_COLOR,
    getBookmarkStore,
    type Bookmark,
    type BookmarkColor,
    type DecryptedBookmark,
} from '../bookmarks/store';

interface BookmarkScope {
    /** True when at least one bookmark exists in localStorage. */
    has: boolean;
    /** True when the master password has been set. */
    initialised: boolean;
    /** True when the store has been unlocked this session. */
    unlocked: boolean;
    /** Public-only list (no decrypted passwords). Always available. */
    publicList: Bookmark[];
    /** Decrypted list — only populated after a successful unlock(). */
    list: DecryptedBookmark[];
    /** Last error from a user action, ready for display. */
    error: string | null;
    /** Available accent colours. Cycle through them in the picker. */
    readonly palette: readonly BookmarkColor[];
    /** Default accent — used when the user does not pick one. */
    readonly defaultColor: BookmarkColor;

    initialise(masterPassword: string, confirmation: string): Promise<boolean>;
    unlock(masterPassword: string): Promise<boolean>;
    lock(): void;
    remove(id: string): void;
    wipe(): void;

    /**
     * Picks a bookmark and dispatches a `bookmarks:fill` browser event.
     * When `autoSubmit` is true, the host page (login) will fill the form
     * AND submit it in the same Livewire round-trip — no extra click.
     */
    fill(id: string, autoSubmit?: boolean): Promise<void>;

    /** Adds (or updates) a bookmark from the current form values. */
    save(data: Omit<DecryptedBookmark, 'id'> & { id?: string }): Promise<string | null>;
}

export function registerBookmarks(Alpine: AlpineLike): void {
    Alpine.directive('bookmarks', (el, _directive, { evaluate }) => {
        // The directive expects the host scope to have an Alpine x-data
        // object. We mutate that scope by adding a `bookmarks` key.
        const scope = evaluate('$data') as Record<string, unknown>;
        const store = getBookmarkStore();

        const reactiveScope: BookmarkScope = Alpine.reactive({
            has: store.hasBookmarks(),
            initialised: store.isInitialised(),
            unlocked: store.isUnlocked(),
            publicList: store.listPublic(),
            list: [],
            error: null,
            palette: BOOKMARK_COLORS,
            defaultColor: DEFAULT_BOOKMARK_COLOR,

            async initialise(masterPassword, confirmation): Promise<boolean> {
                this.error = null;
                if (masterPassword.length < 8) {
                    this.error = 'Master password must be at least 8 characters.';
                    return false;
                }
                if (masterPassword !== confirmation) {
                    this.error = 'Passwords do not match.';
                    return false;
                }
                try {
                    await store.initialise(masterPassword);
                    this.initialised = true;
                    this.unlocked = true;
                    this.has = false;
                    this.list = [];
                    return true;
                } catch (e) {
                    this.error = e instanceof Error ? e.message : String(e);
                    return false;
                }
            },

            async unlock(masterPassword): Promise<boolean> {
                this.error = null;
                const ok = await store.unlock(masterPassword);
                if (! ok) {
                    this.error = 'Wrong master password.';
                    return false;
                }
                this.unlocked = true;
                this.list = await store.list();
                return true;
            },

            lock(): void {
                store.lock();
                this.unlocked = false;
                this.list = [];
            },

            remove(id): void {
                store.remove(id);
                this.has = store.hasBookmarks();
                this.publicList = store.listPublic();
                this.list = this.list.filter(b => b.id !== id);
            },

            wipe(): void {
                store.wipe();
                this.has = false;
                this.initialised = false;
                this.unlocked = false;
                this.publicList = [];
                this.list = [];
                this.error = null;
            },

            async fill(id, autoSubmit = false): Promise<void> {
                const entry = this.list.find(b => b.id === id);
                if (! entry) {
                    return;
                }
                el.dispatchEvent(new CustomEvent('bookmarks:fill', {
                    detail: { ...entry, autoSubmit },
                    bubbles: true,
                }));
            },

            async save(data): Promise<string | null> {
                if (! this.unlocked) {
                    return null;
                }
                try {
                    const id = await store.upsert(data);
                    this.has = true;
                    this.publicList = store.listPublic();
                    this.list = await store.list();
                    return id;
                } catch (e) {
                    this.error = e instanceof Error ? e.message : String(e);
                    return null;
                }
            },
        });

        scope.bookmarks = reactiveScope;

        // Try to restore the cached AES key from sessionStorage so the
        // master password survives navigations within the same tab. When
        // the cached key matches the verification token, the panel jumps
        // straight to the unlocked state — no re-prompt needed.
        void (async () => {
            if (await store.tryRestoreFromSession()) {
                reactiveScope.unlocked = true;
                reactiveScope.list = await store.list();
            }
        })();
    });
}
