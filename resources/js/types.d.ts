declare module '../../vendor/livewire/livewire/dist/livewire.esm' {
    export const Livewire: {
        start(): void;
    };
    export const Alpine: AlpineLike;
}

export interface AlpineLike {
    directive(
        name: string,
        callback: (
            el: HTMLElement,
            options: { value?: string; modifiers: string[]; expression: string },
            ctx: { cleanup: (fn: () => void) => void; evaluate: (expr: string) => unknown },
        ) => void,
    ): void;
}
