import type { AlpineLike } from '../types';

type TooltipPosition = 'top' | 'bottom' | 'left' | 'right';

const POSITIONS: TooltipPosition[] = ['top', 'bottom', 'left', 'right'];

/**
 * Register an `x-tooltip` Alpine directive.
 *
 * Usage:
 *   <span x-tooltip="VARCHAR(255), NOT NULL">id</span>
 *   <span x-tooltip.bottom="…">id</span>
 *   <span x-tooltip.right.noarrow="…">id</span>
 *
 * Modifiers:
 *   - top | bottom | left | right (default: top)
 *   - noarrow : hide the arrow pointing to the trigger
 */
export function registerTooltip(Alpine: AlpineLike): void {
    Alpine.directive('tooltip', (el, { modifiers, expression }, { cleanup }) => {
        const text = expression;
        const showArrow = !modifiers.includes('noarrow');
        const position = (POSITIONS.find((p) => modifiers.includes(p)) ?? 'top') as TooltipPosition;

        // The trigger needs a positioning context for the floating tooltip
        // to anchor against. If the host doesn't already set one, opt in.
        const computedPosition = getComputedStyle(el).position;
        if (!['relative', 'absolute', 'fixed', 'sticky'].includes(computedPosition)) {
            el.style.position = 'relative';
        }

        const onEnter = (): void => {
            // Don't stack tooltips if the user re-enters before leave fires.
            if (el.querySelector('[data-tooltip-root]')) return;
            el.insertAdjacentHTML('beforeend', buildTooltipMarkup(text, position, showArrow));
        };

        const onLeave = (): void => {
            el.querySelector('[data-tooltip-root]')?.remove();
        };

        el.addEventListener('mouseenter', onEnter);
        el.addEventListener('mouseleave', onLeave);
        el.addEventListener('focusin', onEnter);
        el.addEventListener('focusout', onLeave);

        cleanup(() => {
            el.removeEventListener('mouseenter', onEnter);
            el.removeEventListener('mouseleave', onLeave);
            el.removeEventListener('focusin', onEnter);
            el.removeEventListener('focusout', onLeave);
            el.querySelector('[data-tooltip-root]')?.remove();
        });
    });
}

function buildTooltipMarkup(text: string, position: TooltipPosition, arrow: boolean): string {
    const wrapperClasses = ['absolute', 'z-50', 'w-auto', 'text-sm', 'pointer-events-none'];

    switch (position) {
        case 'top':
            wrapperClasses.push('top-0', 'left-1/2', '-translate-x-1/2', '-mt-1', '-translate-y-full');
            break;
        case 'bottom':
            wrapperClasses.push('bottom-0', 'left-1/2', '-translate-x-1/2', '-mb-1', 'translate-y-full');
            break;
        case 'left':
            wrapperClasses.push('top-1/2', '-translate-y-1/2', '-ml-1', 'left-0', '-translate-x-full');
            break;
        case 'right':
            wrapperClasses.push('top-1/2', '-translate-y-1/2', '-mr-1', 'right-0', 'translate-x-full');
            break;
    }

    const arrowMarkup = arrow ? buildArrowMarkup(position) : '';

    return `
        <div data-tooltip-root class="${wrapperClasses.join(' ')}">
            <div class="relative px-2 py-1 text-white rounded bg-zinc-900/95 shadow-lg">
                <p class="block flex-shrink-0 text-xs whitespace-nowrap">${escapeHtml(text)}</p>
                ${arrowMarkup}
            </div>
        </div>
    `;
}

function buildArrowMarkup(position: TooltipPosition): string {
    const containerClasses = ['absolute', 'inline-flex', 'overflow-hidden', 'justify-center', 'items-center'];
    const arrowClasses = ['w-1.5', 'h-1.5', 'transform', 'bg-zinc-900/95'];

    switch (position) {
        case 'top':
            containerClasses.push('bottom-0', '-translate-x-1/2', 'left-1/2', 'w-2.5', 'translate-y-full');
            arrowClasses.push('origin-top-left', '-rotate-45');
            break;
        case 'bottom':
            containerClasses.push('top-0', '-translate-x-1/2', 'left-1/2', 'w-2.5', '-translate-y-full');
            arrowClasses.push('origin-bottom-left', 'rotate-45');
            break;
        case 'left':
            containerClasses.push('right-0', '-translate-y-1/2', 'top-1/2', 'h-2.5', '-mt-px', 'translate-x-full');
            arrowClasses.push('origin-top-left', 'rotate-45');
            break;
        case 'right':
            containerClasses.push('left-0', '-translate-y-1/2', 'top-1/2', 'h-2.5', '-mt-px', '-translate-x-full');
            arrowClasses.push('origin-top-right', '-rotate-45');
            break;
    }

    return `
        <div class="${containerClasses.join(' ')}">
            <div class="${arrowClasses.join(' ')}"></div>
        </div>
    `;
}

function escapeHtml(input: string): string {
    return input
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
