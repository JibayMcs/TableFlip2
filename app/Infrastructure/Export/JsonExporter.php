<?php

declare(strict_types=1);

namespace App\Infrastructure\Export;

use App\Domain\Export\ExportContext;
use App\Domain\Export\ExporterInterface;

class JsonExporter implements ExporterInterface
{
    /**
     * Options:
     *   - layout : 'array' | 'lines'  (default 'lines' for streaming-friendly NDJSON)
     */
    public function export($stream, iterable $rows, ExportContext $context): void
    {
        $layout = (string) $context->option('layout', 'lines');
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        if ($layout === 'array') {
            fwrite($stream, "[\n");
            $first = true;
            foreach ($rows as $row) {
                if (! $first) {
                    fwrite($stream, ",\n");
                }
                fwrite($stream, '  '.json_encode($row, $flags));
                $first = false;
            }
            fwrite($stream, "\n]\n");

            return;
        }

        // Default: JSON Lines (one JSON object per line) — the canonical
        // streaming shape, easy to grep / pipe / load into another tool.
        foreach ($rows as $row) {
            fwrite($stream, json_encode($row, $flags)."\n");
        }
    }
}
