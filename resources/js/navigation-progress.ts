/**
 * Top-of-viewport progress bar shown during Livewire roundtrips.
 *
 * Two trigger sources :
 *   1. SPA navigation events (`livewire:navigate` / `livewire:navigated`) —
 *      fired by <a wire:navigate> links and component redirect(navigate: true).
 *   2. Per-component requests (`Livewire.hook('request', …)`) — covers slow
 *      wire:click / wire:model actions that don't change the URL but still
 *      block the UI waiting for the server.
 *
 * The bar uses ref-counting so concurrent requests don't fight each other.
 */

const BAR_ID = 'tf-nav-progress';

declare global {
    interface Window {
        Livewire?: {
            hook: (name: string, cb: (...args: unknown[]) => void) => void;
        };
    }
}

function ensureBar(): HTMLDivElement {
    let bar = document.getElementById(BAR_ID) as HTMLDivElement | null;
    if (bar) {
        return bar;
    }

    bar = document.createElement('div');
    bar.id = BAR_ID;
    bar.setAttribute('aria-hidden', 'true');
    document.body.appendChild(bar);

    return bar;
}

let trickleTimer: number | undefined;
let progress = 0;
let activeRequests = 0;

function setWidth(pct: number): void {
    ensureBar().style.width = `${pct}%`;
}

function trueStart(): void {
    const bar = ensureBar();

    progress = 10;
    bar.classList.remove('is-finished');
    bar.classList.add('is-active');
    setWidth(progress);

    window.clearInterval(trickleTimer);
    trickleTimer = window.setInterval(() => {
        if (progress >= 85) {
            return;
        }
        const step = (90 - progress) * 0.08;
        progress = Math.min(85, progress + step);
        setWidth(progress);
    }, 200);
}

function trueFinish(): void {
    const bar = ensureBar();
    window.clearInterval(trickleTimer);

    setWidth(100);
    bar.classList.add('is-finished');

    window.setTimeout(() => {
        bar.classList.remove('is-active', 'is-finished');
        progress = 0;
        setWidth(0);
    }, 300);
}

function begin(): void {
    activeRequests++;
    if (activeRequests === 1) {
        trueStart();
    }
}

function end(): void {
    activeRequests = Math.max(0, activeRequests - 1);
    if (activeRequests === 0) {
        trueFinish();
    }
}

export function registerNavigationProgress(): void {
    // 1) SPA navigations
    document.addEventListener('livewire:navigate', begin);
    document.addEventListener('livewire:navigated', end);

    // 2) Per-component requests — Livewire v4 exposes a `request` hook that
    // fires once per round-trip with succeed/fail callbacks.
    const wireUp = () => {
        if (!window.Livewire?.hook) {
            return false;
        }

        window.Livewire.hook('request', ({ succeed, fail }: { succeed: (cb: () => void) => void; fail: (cb: () => void) => void }) => {
            begin();
            succeed(() => end());
            fail(() => end());
        });

        return true;
    };

    if (!wireUp()) {
        // Livewire isn't on the window yet (we register before Livewire.start()).
        // Retry once it has booted.
        document.addEventListener('livewire:init', wireUp);
    }
}
