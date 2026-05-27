import type { AlpineLike } from '../types';
import { createSqlEditor, type EditorHandle } from '../codemirror/sql-editor';
import type { SchemaConfig } from '../codemirror/types';

interface DirectiveConfig {
    dialect: string;
    schema: SchemaConfig;
    initialContent?: string;
    defaultTable?: string;
    /** Custom event dispatched on the host element when Cmd/Ctrl+Enter is pressed. */
    executeEvent?: string;
    /** Custom event dispatched on every content change. */
    changeEvent?: string;
}

/**
 * Register an `x-sql-editor` Alpine directive that mounts a CodeMirror SQL
 * editor inside the element.
 *
 * Usage:
 *   <div x-sql-editor='{
 *       "dialect": "mariadb",
 *       "schema": {"users": ["id","email"]},
 *       "initialContent": "SELECT * FROM users",
 *       "executeEvent": "sql-execute",
 *       "changeEvent": "sql-change"
 *   }' wire:ignore></div>
 *
 * The host element also responds to two window events:
 *   - `sql-editor-set-content` { detail: { selector, sql } }
 *   - `sql-editor-set-schema`  { detail: { selector, dialect, schema, defaultTable? } }
 *
 * `selector` is a CSS selector matching the editor's host; this lets a parent
 * Livewire component target a specific editor when several may live on the page.
 */
export function registerSqlEditor(Alpine: AlpineLike): void {
    Alpine.directive('sql-editor', (el, { expression }, { cleanup }) => {
        let config: DirectiveConfig;
        try {
            config = JSON.parse(expression) as DirectiveConfig;
        } catch {
            console.error('[x-sql-editor] invalid JSON configuration:', expression);
            return;
        }

        // CodeMirror will render its own UI inside this element. Make sure the
        // host has a usable height so the editor doesn't collapse.
        if (!el.style.minHeight && getComputedStyle(el).height === 'auto') {
            el.style.minHeight = '12rem';
        }

        const handle: EditorHandle = createSqlEditor({
            parent: el,
            initialContent: config.initialContent ?? '',
            dialect: config.dialect,
            schema: config.schema ?? {},
            defaultTable: config.defaultTable,
            onExecute: (sql) => {
                if (config.executeEvent) {
                    el.dispatchEvent(new CustomEvent(config.executeEvent, { detail: { sql }, bubbles: true }));
                }
            },
            onChange: (sql) => {
                if (config.changeEvent) {
                    el.dispatchEvent(new CustomEvent(config.changeEvent, { detail: { sql }, bubbles: true }));
                }
            },
        });

        const onSetContent = (event: Event): void => {
            const ce = event as CustomEvent<{ selector?: string; sql: string }>;
            if (ce.detail.selector && !el.matches(ce.detail.selector)) return;
            if (handle.getContent() === ce.detail.sql) return;
            handle.setContent(ce.detail.sql);
        };

        const onSetSchema = (event: Event): void => {
            const ce = event as CustomEvent<{
                selector?: string;
                dialect: string;
                schema: SchemaConfig;
                defaultTable?: string;
            }>;
            if (ce.detail.selector && !el.matches(ce.detail.selector)) return;
            handle.setSchema(ce.detail.dialect, ce.detail.schema, ce.detail.defaultTable);
        };

        window.addEventListener('sql-editor-set-content', onSetContent);
        window.addEventListener('sql-editor-set-schema', onSetSchema);

        cleanup(() => {
            window.removeEventListener('sql-editor-set-content', onSetContent);
            window.removeEventListener('sql-editor-set-schema', onSetSchema);
            handle.destroy();
        });
    });
}
