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
 *
 * Implementation note : the tooltip is rendered with `position: fixed` and
 * portal-ed into <body> so any ancestor with `overflow: hidden|auto|scroll`
 * (sidebars, modals, table cells…) doesn't clip it. Coordinates are
 * computed from the trigger's getBoundingClientRect() on each show.
 */
export function registerTooltip(Alpine: AlpineLike): void {
    Alpine.directive('tooltip', (el, { modifiers, expression }, { cleanup }) => {
        const text = expression;
        const showArrow = !modifiers.includes('noarrow');
        const position = (POSITIONS.find((p) => modifiers.includes(p)) ?? 'top') as TooltipPosition;

        let tooltipEl: HTMLDivElement | null = null;

        const remove = (): void => {
            if (tooltipEl) {
                tooltipEl.remove();
                tooltipEl = null;
            }
        };

        const show = (): void => {
            if (tooltipEl) return;
            tooltipEl = buildTooltip(text, position, showArrow);
            document.body.appendChild(tooltipEl);
            positionTooltip(tooltipEl, el as HTMLElement, position);
        };

        const reposition = (): void => {
            if (tooltipEl) {
                positionTooltip(tooltipEl, el as HTMLElement, position);
            }
        };

        el.addEventListener('mouseenter', show);
        el.addEventListener('mouseleave', remove);
        el.addEventListener('focusin', show);
        el.addEventListener('focusout', remove);
        // Reposition on scroll / resize so the tooltip tracks the trigger.
        window.addEventListener('scroll', reposition, true);
        window.addEventListener('resize', reposition);

        cleanup(() => {
            el.removeEventListener('mouseenter', show);
            el.removeEventListener('mouseleave', remove);
            el.removeEventListener('focusin', show);
            el.removeEventListener('focusout', remove);
            window.removeEventListener('scroll', reposition, true);
            window.removeEventListener('resize', reposition);
            remove();
        });
    });
}

function buildTooltip(text: string, position: TooltipPosition, arrow: boolean): HTMLDivElement {
    const root = document.createElement('div');
    root.dataset.tooltipRoot = '';
    root.setAttribute('role', 'tooltip');
    root.style.position = 'fixed';
    root.style.zIndex = '9999';
    root.style.pointerEvents = 'none';

    root.innerHTML = `
        <div class="relative px-2 py-1 text-white rounded bg-zinc-900/95 shadow-lg">
            <p class="block flex-shrink-0 text-xs whitespace-nowrap">${escapeHtml(text)}</p>
            ${arrow ? arrowHtml(position) : ''}
        </div>
    `;

    return root;
}

function positionTooltip(tooltip: HTMLDivElement, trigger: HTMLElement, position: TooltipPosition): void {
    const rect = trigger.getBoundingClientRect();
    const ttRect = tooltip.getBoundingClientRect();
    const gap = 6;

    let top = 0;
    let left = 0;

    switch (position) {
        case 'top':
            top = rect.top - ttRect.height - gap;
            left = rect.left + rect.width / 2 - ttRect.width / 2;
            break;
        case 'bottom':
            top = rect.bottom + gap;
            left = rect.left + rect.width / 2 - ttRect.width / 2;
            break;
        case 'left':
            top = rect.top + rect.height / 2 - ttRect.height / 2;
            left = rect.left - ttRect.width - gap;
            break;
        case 'right':
            top = rect.top + rect.height / 2 - ttRect.height / 2;
            left = rect.right + gap;
            break;
    }

    // Clamp to viewport so a tooltip near the edge doesn't fly off-screen.
    const pad = 4;
    left = Math.max(pad, Math.min(left, window.innerWidth - ttRect.width - pad));
    top = Math.max(pad, Math.min(top, window.innerHeight - ttRect.height - pad));

    tooltip.style.top = `${top}px`;
    tooltip.style.left = `${left}px`;
}

function arrowHtml(position: TooltipPosition): string {
    const container = ['absolute', 'inline-flex', 'overflow-hidden', 'justify-center', 'items-center'];
    const arrow = ['w-1.5', 'h-1.5', 'transform', 'bg-zinc-900/95'];

    switch (position) {
        case 'top':
            container.push('bottom-0', '-translate-x-1/2', 'left-1/2', 'w-2.5', 'translate-y-full');
            arrow.push('origin-top-left', '-rotate-45');
            break;
        case 'bottom':
            container.push('top-0', '-translate-x-1/2', 'left-1/2', 'w-2.5', '-translate-y-full');
            arrow.push('origin-bottom-left', 'rotate-45');
            break;
        case 'left':
            container.push('right-0', '-translate-y-1/2', 'top-1/2', 'h-2.5', '-mt-px', 'translate-x-full');
            arrow.push('origin-top-left', 'rotate-45');
            break;
        case 'right':
            container.push('left-0', '-translate-y-1/2', 'top-1/2', 'h-2.5', '-mt-px', '-translate-x-full');
            arrow.push('origin-top-right', '-rotate-45');
            break;
    }

    return `<div class="${container.join(' ')}"><div class="${arrow.join(' ')}"></div></div>`;
}

function escapeHtml(input: string): string {
    return input
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
