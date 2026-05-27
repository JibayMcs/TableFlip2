<?php

declare(strict_types=1);

namespace App\Application\Sql;

/**
 * Inspects a SQL string to flag operations that can quietly cause data loss.
 *
 * The detector is conservative: it strips comments and string/identifier
 * literals before pattern-matching so it cannot be fooled by a keyword that
 * only appears inside a string. False negatives are preferred over false
 * positives — when we flag, the UI asks for an explicit confirmation, so a
 * missed match just means "no extra prompt", not "data lost".
 */
class DestructiveSqlDetector
{
    /**
     * @return array<int, string>  Human-readable reasons for each destructive
     *                             operation detected, or empty when the SQL
     *                             can run without an extra confirmation.
     */
    public function analyze(string $sql): array
    {
        $stripped = $this->stripLiteralsAndComments($sql);
        $reasons = [];

        if (preg_match_all('/\b(DROP)\s+(DATABASE|SCHEMA|TABLE|VIEW|INDEX|FUNCTION|PROCEDURE|TRIGGER)\b/i', $stripped, $m)) {
            foreach ($m[0] as $hit) {
                $reasons[] = strtoupper(trim($hit)).' will permanently remove the target.';
            }
        }

        if (preg_match_all('/\bTRUNCATE\s+(?:TABLE\s+)?\S+/i', $stripped, $m)) {
            foreach ($m[0] as $hit) {
                $reasons[] = 'TRUNCATE empties the table without firing row triggers and cannot be rolled back on MyISAM.';
            }
        }

        if ($this->hasUnsafeWriteWithoutWhere($stripped, 'DELETE')) {
            $reasons[] = 'DELETE without WHERE will remove every row in the table.';
        }

        if ($this->hasUnsafeWriteWithoutWhere($stripped, 'UPDATE')) {
            $reasons[] = 'UPDATE without WHERE will rewrite every row in the table.';
        }

        if (preg_match_all('/\bALTER\s+TABLE\b/i', $stripped)) {
            $reasons[] = 'ALTER TABLE modifies the schema and may rewrite the entire table.';
        }

        if (preg_match_all('/\bRENAME\s+TABLE\b/i', $stripped)) {
            $reasons[] = 'RENAME TABLE changes the table name; references may break.';
        }

        return array_values(array_unique($reasons));
    }

    public function isDestructive(string $sql): bool
    {
        return $this->analyze($sql) !== [];
    }

    /**
     * Detect "<verb> FROM/<table> ... ;" or "<verb> table ... ;" without a
     * subsequent WHERE clause inside the same statement. Operates on the
     * literal-stripped SQL so quoted contents cannot fool the regex.
     */
    private function hasUnsafeWriteWithoutWhere(string $sql, string $verb): bool
    {
        $verb = preg_quote($verb, '/');
        $statements = preg_split('/;\s*/', $sql) ?: [];

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || ! preg_match('/^\s*'.$verb.'\b/i', $stmt)) {
                continue;
            }
            if (! preg_match('/\bWHERE\b/i', $stmt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove SQL comments, then replace string and identifier literals with
     * placeholders so a keyword inside `'DROP TABLE'` is not flagged.
     */
    private function stripLiteralsAndComments(string $sql): string
    {
        // -- line comments
        $sql = preg_replace('/--[^\n]*/', ' ', $sql) ?? $sql;
        // /* block comments */ (non-greedy)
        $sql = preg_replace('/\/\*.*?\*\//s', ' ', $sql) ?? $sql;
        // 'single-quoted strings' (with doubled-quote escapes)
        $sql = preg_replace("/'(?:''|[^'])*'/", "''", $sql) ?? $sql;
        // "double-quoted" + `backtick` + [bracket] identifiers
        $sql = preg_replace('/"(?:""|[^"])*"/', '""', $sql) ?? $sql;
        $sql = preg_replace('/`(?:``|[^`])*`/', '``', $sql) ?? $sql;
        $sql = preg_replace('/\[[^\]]*\]/', '[]', $sql) ?? $sql;

        return $sql;
    }
}
