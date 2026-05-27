import type { Alpine } from 'alpinejs';
import cytoscape, { type Core, type ElementDefinition, type LayoutOptions } from 'cytoscape';
// @ts-expect-error — extensions ship without bundled types
import dagre from 'cytoscape-dagre';
// @ts-expect-error
import fcose from 'cytoscape-fcose';
// @ts-expect-error
import coseBilkent from 'cytoscape-cose-bilkent';

// Register layout extensions once. Safe to call multiple times — cytoscape
// guards against double-registration internally.
cytoscape.use(dagre);
cytoscape.use(fcose);
cytoscape.use(coseBilkent);

type CytoscapeData = {
    nodes: ElementDefinition[];
    edges: ElementDefinition[];
};

type CytoscapeBinding = {
    cy: Core | null;
};

const REGISTRY = new WeakMap<HTMLElement, CytoscapeBinding>();

/**
 * Build the layout config for a given preset name.
 * dagre  → top-down hierarchical (default, ERD-style)
 * fcose  → fast force-directed, good for huge graphs (200+ nodes)
 * cose   → organic spring layout, nicer on small graphs
 */
function layoutFor(name: string): LayoutOptions {
    switch (name) {
        case 'fcose':
            return {
                name: 'fcose',
                animate: false,
                fit: true,
                padding: 30,
                nodeSeparation: 90,
                idealEdgeLength: 120,
                nodeRepulsion: 8000,
            } as unknown as LayoutOptions;
        case 'cose':
            return {
                name: 'cose-bilkent',
                animate: false,
                fit: true,
                padding: 30,
                nodeRepulsion: 10000,
                idealEdgeLength: 120,
            } as unknown as LayoutOptions;
        case 'dagre':
        default:
            return {
                name: 'dagre',
                rankDir: 'TB',
                nodeSep: 60,
                edgeSep: 20,
                rankSep: 90,
                animate: false,
                fit: true,
                padding: 30,
            } as unknown as LayoutOptions;
    }
}

function styleSheet(): cytoscape.Stylesheet[] {
    return [
        {
            selector: 'node',
            style: {
                shape: 'round-rectangle',
                'background-color': '#ffffff',
                'border-color': '#d4d4d8',
                'border-width': 1,
                'corner-radius': '6',
                label: 'data(label)',
                'text-valign': 'center',
                'text-halign': 'center',
                color: '#18181b',
                'font-family': 'Instrument Sans, ui-sans-serif, system-ui, sans-serif',
                'font-size': 12,
                'font-weight': 500,
                'padding-top': '8px',
                'padding-bottom': '8px',
                'padding-left': '12px',
                'padding-right': '12px',
                width: 'label',
                height: 'label',
                'min-width': 80,
                'text-wrap': 'wrap',
                'overlay-padding': '4px',
            } as cytoscape.Css.Node,
        },
        {
            selector: 'node:selected',
            style: {
                'border-color': '#2563eb',
                'border-width': 2,
                'background-color': '#eff6ff',
            } as cytoscape.Css.Node,
        },
        {
            selector: 'node.dimmed',
            style: { opacity: 0.18 } as cytoscape.Css.Node,
        },
        {
            selector: 'node.highlight',
            style: {
                'border-color': '#16a34a',
                'border-width': 2,
                'background-color': '#f0fdf4',
            } as cytoscape.Css.Node,
        },
        {
            selector: 'edge',
            style: {
                width: 1.2,
                'line-color': '#a1a1aa',
                'target-arrow-color': '#a1a1aa',
                'target-arrow-shape': 'triangle',
                'curve-style': 'bezier',
                'arrow-scale': 1,
                opacity: 0.7,
            } as cytoscape.Css.Edge,
        },
        {
            selector: 'edge:selected, edge.highlight',
            style: {
                width: 2,
                'line-color': '#2563eb',
                'target-arrow-color': '#2563eb',
                opacity: 1,
                label: 'data(label)',
                'font-size': 9,
                'text-background-color': '#ffffff',
                'text-background-opacity': 0.9,
                'text-background-padding': '2px',
                color: '#3f3f46',
            } as cytoscape.Css.Edge,
        },
        {
            selector: 'edge.dimmed',
            style: { opacity: 0.08 } as cytoscape.Css.Edge,
        },
    ];
}

/**
 * Alpine directive `x-cytoscape` — turns its host <div> into an interactive
 * graph. The expression must evaluate to an object with `nodes`, `edges`,
 * `layout` and (optional) `highlight` keys. The directive reactively
 * re-renders on changes and emits CustomEvents the host can listen to :
 *   - `cy:node-selected` (detail: node data) → side panel updates
 *   - `cy:node-deselected` → side panel closes
 */
export function registerCytoscape(alpine: Alpine): void {
    alpine.directive('cytoscape', (el, { expression }, { evaluateLater, effect, cleanup }) => {
        const host = el as HTMLDivElement;
        const getPayload = evaluateLater<{
            nodes: ElementDefinition[];
            edges: ElementDefinition[];
            layout: string;
            highlight?: string;
        }>(expression);

        let binding: CytoscapeBinding = { cy: null };
        REGISTRY.set(host, binding);

        // Cytoscape measures its canvas from host.clientHeight at init time
        // AND doesn't react to its container resizing. We watch the host
        // with a ResizeObserver and call cy.resize() + a debounced refit so
        // window resizes / panel toggles don't leave a misaligned canvas.
        let resizeDebounce: number | undefined;
        const observer = new ResizeObserver(() => {
            if (!binding.cy) return;
            binding.cy.resize();
            window.clearTimeout(resizeDebounce);
            resizeDebounce = window.setTimeout(() => binding.cy?.fit(undefined, 30), 150);
        });
        observer.observe(host);

        cleanup(() => {
            observer.disconnect();
            window.clearTimeout(resizeDebounce);
            if (binding.cy) {
                binding.cy.destroy();
                binding.cy = null;
            }
            REGISTRY.delete(host);
        });

        let lastSignature = '';
        let lastLayout = '';
        let lastHighlight = '';

        effect(() => {
            getPayload((payload) => {
                if (!payload) return;

                const data: CytoscapeData = { nodes: payload.nodes || [], edges: payload.edges || [] };
                const layoutName = payload.layout || 'dagre';
                const highlight = (payload.highlight || '').trim().toLowerCase();

                // Cheap signature so we don't rebuild on highlight-only changes.
                const signature = `${data.nodes.length}:${data.edges.length}:${data.nodes.map(n => n.data?.id).join(',')}`;

                if (signature !== lastSignature) {
                    lastSignature = signature;
                    lastLayout = layoutName;

                    if (binding.cy) {
                        binding.cy.destroy();
                    }

                    if (data.nodes.length === 0) {
                        host.innerHTML = '<div class="text-xs text-zinc-400 italic p-8 text-center">No tables to render.</div>';
                        return;
                    }

                    host.innerHTML = '';

                    binding.cy = cytoscape({
                        container: host,
                        elements: [...data.nodes, ...data.edges],
                        style: styleSheet(),
                        layout: layoutFor(layoutName),
                        wheelSensitivity: 0.3,
                        minZoom: 0.05,
                        maxZoom: 4,
                    });

                    // Highlight neighbours on tap; clear on background tap.
                    binding.cy.on('tap', 'node', (evt) => {
                        const node = evt.target;
                        const neighborhood = node.closedNeighborhood();
                        binding.cy!.elements().addClass('dimmed').removeClass('highlight');
                        neighborhood.removeClass('dimmed').addClass('highlight');
                        host.dispatchEvent(new CustomEvent('cy:node-selected', { detail: node.data(), bubbles: true }));
                    });
                    binding.cy.on('tap', (evt) => {
                        if (evt.target === binding.cy) {
                            binding.cy!.elements().removeClass('dimmed highlight');
                            host.dispatchEvent(new CustomEvent('cy:node-deselected', { bubbles: true }));
                        }
                    });
                } else if (layoutName !== lastLayout && binding.cy) {
                    lastLayout = layoutName;
                    binding.cy.layout(layoutFor(layoutName)).run();
                }

                // Apply / clear search highlight without rebuilding the graph.
                if (highlight !== lastHighlight && binding.cy) {
                    lastHighlight = highlight;
                    binding.cy.batch(() => {
                        binding.cy!.nodes().removeClass('highlight dimmed');
                        if (highlight !== '') {
                            const matching = binding.cy!.nodes().filter((n) => {
                                const label = String(n.data('label') || '').toLowerCase();
                                return label.includes(highlight);
                            });
                            if (matching.nonempty()) {
                                binding.cy!.nodes().not(matching).addClass('dimmed');
                                matching.addClass('highlight');
                            }
                        }
                    });
                }
            });
        });
    });
}

/**
 * Resolve the Cytoscape instance bound to a DOM element — used by the
 * Livewire view to trigger fit / png export from outside Alpine scope.
 */
export function cyInstance(el: HTMLElement | null): Core | null {
    if (!el) return null;
    return REGISTRY.get(el)?.cy ?? null;
}

// Expose for inline Alpine handlers in the Blade view.
declare global {
    interface Window {
        tfCytoscape?: { instance: typeof cyInstance };
    }
}
if (typeof window !== 'undefined') {
    window.tfCytoscape = { instance: cyInstance };
}
