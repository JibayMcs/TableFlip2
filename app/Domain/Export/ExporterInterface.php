<?php

declare(strict_types=1);

namespace App\Domain\Export;

interface ExporterInterface
{
    /**
     * Stream the given rows into the open writable resource $stream.
     *
     * Implementations MUST handle empty result sets gracefully (write headers
     * only for CSV, opening/closing brackets for JSON array, no INSERT for SQL).
     *
     * @param  resource  $stream  an open writable stream (typically opened via fopen('php://output') or a file)
     * @param  iterable<int, array<string, mixed>>  $rows  the rows to serialise (Generator preferred for memory)
     * @param  ExportContext  $context  schema + format-specific options
     */
    public function export($stream, iterable $rows, ExportContext $context): void;
}
