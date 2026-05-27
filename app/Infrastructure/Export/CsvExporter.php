<?php

declare(strict_types=1);

namespace App\Infrastructure\Export;

use App\Domain\Export\ExportContext;
use App\Domain\Export\ExporterInterface;
use League\Csv\Writer;

class CsvExporter implements ExporterInterface
{
    /**
     * Options:
     *   - delimiter        : char, default ','
     *   - enclosure        : char, default '"'
     *   - include_header   : bool, default true
     */
    public function export($stream, iterable $rows, ExportContext $context): void
    {
        $csv = Writer::createFromStream($stream);
        $csv->setDelimiter((string) $context->option('delimiter', ','));
        $csv->setEnclosure((string) $context->option('enclosure', '"'));

        if ((bool) $context->option('include_header', true) && $context->columns !== []) {
            $csv->insertOne($context->columns);
        }

        foreach ($rows as $row) {
            $csv->insertOne(array_map(
                static fn ($v) => $v === null ? '' : (string) $v,
                array_map(static fn ($col) => $row[$col] ?? null, $context->columns),
            ));
        }
    }
}
