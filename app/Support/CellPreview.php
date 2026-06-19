<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalises a freshly-fetched cell value for display in a *preview* grid
 * (SQL editor results, table Data view). Two jobs:
 *
 *  - Binary blobs (varbinary / BLOB / image / bytea storing a PDF, a photo,
 *    …) collapse to a short marker. Raw bytes are never UTF-8 and would
 *    otherwise break the JSON snapshot and litter the HTML with garbage.
 *  - Long text is cut, multibyte-safe, to a byte budget with an ellipsis.
 *
 * The server-side fetch is already bounded for SQL Server via SET TEXTSIZE
 * (see AbstractDatabaseDriver) — this is the in-PHP companion that decides
 * how a (possibly already truncated) value is rendered.
 */
final class CellPreview
{
    /** Stand-in shown instead of raw binary bytes. */
    public const BINARY_MARKER = '‹binary data›';

    /**
     * @return array{0: mixed, 1: bool}  [value, wasTruncatedText]
     *         wasTruncatedText is true only for text cut short (which the UI
     *         may offer to expand) — never for the binary marker.
     */
    public static function cap(mixed $value, int $maxBytes): array
    {
        if (! is_string($value) || $value === '') {
            return [$value, false];
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            return [self::BINARY_MARKER, false];
        }

        if (strlen($value) > $maxBytes) {
            return [mb_strcut($value, 0, $maxBytes).'…', true];
        }

        return [$value, false];
    }
}
