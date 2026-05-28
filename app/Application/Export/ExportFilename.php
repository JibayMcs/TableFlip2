<?php

declare(strict_types=1);

namespace App\Application\Export;

/**
 * Resolves an export filename template into a concrete name.
 *
 * Supported placeholders (case-sensitive, matched as-is) :
 *   - @DATABASE@  — source database name
 *   - @TABLE@     — source table name (empty for multi-table dumps)
 *   - @DRIVER@    — driver short name (mysql / pgsql / sqlsrv / sqlite)
 *   - @DATE@      — YYYY-MM-DD
 *   - @TIME@      — HH-MM-SS
 *   - @DATETIME@  — YYYY-MM-DD_HH-MM-SS
 *   - @USER@      — user identifier (web id or direct uuid)
 *
 * Unknown placeholders are left untouched (so the user knows they made a typo).
 *
 * The resulting name is slugified : any character not in [A-Za-z0-9._-] is
 * replaced by `_` so it's safe on every common filesystem.
 */
class ExportFilename
{
    public const DEFAULT_TEMPLATE = '@DATABASE@-@DATETIME@';

    /**
     * @param  array<string, string|null>  $vars
     */
    public static function resolve(string $template, array $vars): string
    {
        $template = $template !== '' ? $template : self::DEFAULT_TEMPLATE;
        $now = now();

        $replacements = [
            '@DATABASE@' => (string) ($vars['database'] ?? ''),
            '@TABLE@' => (string) ($vars['table'] ?? ''),
            '@DRIVER@' => (string) ($vars['driver'] ?? ''),
            '@USER@' => (string) ($vars['user'] ?? ''),
            '@DATE@' => $now->format('Y-m-d'),
            '@TIME@' => $now->format('H-i-s'),
            '@DATETIME@' => $now->format('Y-m-d_H-i-s'),
        ];

        $resolved = strtr($template, $replacements);

        return self::slugify($resolved);
    }

    /**
     * Appends the format extension + the compression suffix.
     */
    public static function withExtension(string $base, string $formatExtension, string $compression = 'none'): string
    {
        $name = $base.'.'.ltrim($formatExtension, '.');

        return match ($compression) {
            'gzip' => $name.'.gz',
            'zip' => self::stripExt($base).'.zip',
            default => $name,
        };
    }

    private static function slugify(string $value): string
    {
        // Keep dots so multi-extension names like `db.users` survive, but
        // replace anything else weird with an underscore.
        $value = preg_replace('/[^A-Za-z0-9._-]+/u', '_', $value) ?? $value;
        $value = trim($value, '_-');

        return $value !== '' ? $value : 'export';
    }

    private static function stripExt(string $name): string
    {
        $dot = strrpos($name, '.');

        return $dot === false ? $name : substr($name, 0, $dot);
    }
}
