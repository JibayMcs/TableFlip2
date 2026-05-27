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

/**
 * Light, project-themed editor styling. Backgrounds and borders match the
 * Tailwind palette used everywhere else (zinc 50/200/900) so the editor
 * blends into the surrounding panels.
 */
const lightTheme = EditorView.theme(
    {
        '&': {
            height: '100%',
            fontSize: '13px',
            backgroundColor: '#ffffff',
            color: '#18181b',
        },
        '.cm-scroller': { fontFamily: "'Fira Code', ui-monospace, SFMono-Regular, monospace" },
        // Defensive: re-enable text selection in case a `select-none` rule
        // somewhere in the cascade silently disabled it (Tailwind reset, parent
        // utility class…).
        '.cm-content': {
            caretColor: '#18181b',
            userSelect: 'text',
            WebkitUserSelect: 'text',
        },
        '.cm-gutters': {
            backgroundColor: '#fafafa',
            color: '#a1a1aa',
            borderRight: '1px solid #e4e4e7',
        },
        '.cm-activeLineGutter': { backgroundColor: '#f4f4f5', color: '#52525b' },
        // Push the selection layer above the text/active-line backgrounds so
        // drag-selected ranges are visible.
        '.cm-selectionLayer': { zIndex: '1' },
        '.cm-selectionBackground, &.cm-focused .cm-selectionBackground, .cm-content ::selection': {
            backgroundColor: '#bfdbfe',
        },
        '.cm-cursor': { borderLeftColor: '#18181b' },
        '.cm-tooltip-autocomplete': {
            border: '1px solid #e4e4e7',
            borderRadius: '6px',
            backgroundColor: '#ffffff',
            boxShadow: '0 4px 12px rgba(0,0,0,0.06)',
            fontFamily: 'inherit',
            fontSize: '12px',
        },
        '.cm-tooltip-autocomplete > ul > li[aria-selected]': {
            backgroundColor: '#18181b',
            color: '#ffffff',
        },
    },
    { dark: false },
);

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
            lightTheme,
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
