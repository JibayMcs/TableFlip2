<?php

declare(strict_types=1);

namespace App\Application\Connections;

final class ConnectionColors
{
    /** Tailwind-aligned hex presets (zinc, red, amber, emerald, blue, violet, pink, cyan). */
    public const PRESETS = [
        '#71717a',
        '#ef4444',
        '#f59e0b',
        '#10b981',
        '#3b82f6',
        '#8b5cf6',
        '#ec4899',
        '#06b6d4',
    ];

    public const DEFAULT = '#71717a';

    public static function isValid(string $hex): bool
    {
        return in_array($hex, self::PRESETS, true);
    }
}
