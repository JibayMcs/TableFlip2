import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
import { MariaSQL, MSSQL, MySQL, PostgreSQL, SQLDialect, SQLite, sql } from '@codemirror/lang-sql';
import { defaultHighlightStyle, syntaxHighlighting } from '@codemirror/language';
import { Compartment, EditorState } from '@codemirror/state';
import {
    EditorView,
    drawSelection,
    highlightActiveLineGutter,
    keymap,
    lineNumbers,
} from '@codemirror/view';
import {
    autocompletion,
    closeBrackets,
    closeBracketsKeymap,
    completionKeymap,
} from '@codemirror/autocomplete';

import type { SchemaConfig } from './types';

/** Lowercased driver identifier coming from TableFlip → CodeMirror dialect. */
const DIALECT_MAP: Record<string, SQLDialect> = {
    mysql: MySQL,
    mariadb: MariaSQL,
    pgsql: PostgreSQL,
    sqlsrv: MSSQL,
    sqlite: SQLite,
};

export interface CreateEditorOptions {
    parent: HTMLElement;
    initialContent?: string;
    dialect?: string;
    schema?: SchemaConfig;
    defaultTable?: string;
    onExecute?: (sql: string) => void;
    onChange?: (sql: string) => void;
}

export interface EditorHandle {
    view: EditorView;
    getContent(): string;
    setContent(sql: string): void;
    setSchema(dialectName: string, schema: SchemaConfig, defaultTable?: string): void;
    destroy(): void;
}

function isDark(): boolean {
    return typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
}

/**
 * Project-themed editor styling. Backgrounds and borders match the Tailwind
 * palette (zinc 50/200/900 in light, zinc 900/800/100 in dark) so the editor
 * blends into the surrounding panels.
 *
 * The theme is built fresh at editor creation time — there's no live theme
 * switching (the user toggles in /profile which triggers a full reload).
 */
function projectTheme() {
    const dark = isDark();
    const c = dark
        ? {
              bg: '#18181b',
              fg: '#f4f4f5',
              caret: '#fafafa',
              gutterBg: '#27272a',
              gutterFg: '#52525b',
              gutterBorder: '#3f3f46',
              activeGutterBg: '#3f3f46',
              activeGutterFg: '#a1a1aa',
              selection: '#1e40af',
              tooltipBg: '#27272a',
              tooltipBorder: '#3f3f46',
              tooltipSelectedBg: '#3b82f6',
              tooltipSelectedFg: '#ffffff',
          }
        : {
              bg: '#ffffff',
              fg: '#18181b',
              caret: '#18181b',
              gutterBg: '#fafafa',
              gutterFg: '#a1a1aa',
              gutterBorder: '#e4e4e7',
              activeGutterBg: '#f4f4f5',
              activeGutterFg: '#52525b',
              selection: '#bfdbfe',
              tooltipBg: '#ffffff',
              tooltipBorder: '#e4e4e7',
              tooltipSelectedBg: '#18181b',
              tooltipSelectedFg: '#ffffff',
          };

    return EditorView.theme(
        {
            '&': {
                height: '100%',
                fontSize: '13px',
                backgroundColor: c.bg,
                color: c.fg,
            },
            '.cm-scroller': { fontFamily: "'Fira Code', ui-monospace, SFMono-Regular, monospace" },
            '.cm-content': {
                caretColor: c.caret,
                userSelect: 'text',
                WebkitUserSelect: 'text',
            },
            '.cm-gutters': {
                backgroundColor: c.gutterBg,
                color: c.gutterFg,
                borderRight: `1px solid ${c.gutterBorder}`,
            },
            '.cm-activeLineGutter': { backgroundColor: c.activeGutterBg, color: c.activeGutterFg },
            '.cm-selectionLayer': { zIndex: '1' },
            '.cm-selectionBackground, &.cm-focused .cm-selectionBackground, .cm-content ::selection': {
                backgroundColor: c.selection,
            },
            '.cm-cursor': { borderLeftColor: c.caret },
            '.cm-tooltip-autocomplete': {
                border: `1px solid ${c.tooltipBorder}`,
                borderRadius: '6px',
                backgroundColor: c.tooltipBg,
                boxShadow: '0 4px 12px rgba(0,0,0,0.18)',
                fontFamily: 'inherit',
                fontSize: '12px',
                color: c.fg,
            },
            '.cm-tooltip-autocomplete > ul > li[aria-selected]': {
                backgroundColor: c.tooltipSelectedBg,
                color: c.tooltipSelectedFg,
            },
        },
        { dark },
    );
}

export function createSqlEditor(opts: CreateEditorOptions): EditorHandle {
    const sqlCompartment = new Compartment();

    const buildSqlExtension = (dialectName: string, schema: SchemaConfig, defaultTable?: string) =>
        sql({
            dialect: DIALECT_MAP[dialectName.toLowerCase()] ?? MySQL,
            schema,
            defaultTable,
            upperCaseKeywords: true,
        });

    const state = EditorState.create({
        doc: opts.initialContent ?? '',
        extensions: [
            lineNumbers(),
            highlightActiveLineGutter(),
            drawSelection(),
            history(),
            closeBrackets(),
            autocompletion({ activateOnTyping: true }),
            // Order matters: our shortcut must win against defaultKeymap.
            keymap.of([
                {
                    key: 'Mod-Enter',
                    preventDefault: true,
                    run: (view) => {
                        opts.onExecute?.(view.state.doc.toString());
                        return true;
                    },
                },
                ...completionKeymap,
                ...closeBracketsKeymap,
                ...defaultKeymap,
                ...historyKeymap,
                indentWithTab,
            ]),
            sqlCompartment.of(buildSqlExtension(opts.dialect ?? 'mysql', opts.schema ?? {}, opts.defaultTable)),
            syntaxHighlighting(defaultHighlightStyle),
            projectTheme(),
            EditorView.updateListener.of((update) => {
                if (update.docChanged && opts.onChange) {
                    opts.onChange(update.state.doc.toString());
                }
            }),
        ],
    });

    const view = new EditorView({ state, parent: opts.parent });

    return {
        view,
        getContent: () => view.state.doc.toString(),
        setContent: (sqlText: string) => {
            view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: sqlText } });
        },
        setSchema: (dialectName, schema, defaultTable) => {
            view.dispatch({
                effects: sqlCompartment.reconfigure(buildSqlExtension(dialectName, schema, defaultTable)),
            });
        },
        destroy: () => view.destroy(),
    };
}
