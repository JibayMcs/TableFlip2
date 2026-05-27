<?php

declare(strict_types=1);

namespace App\Infrastructure\Export;

use App\Domain\Export\ExporterInterface;
use App\Domain\Export\ExportFormat;

class ExporterFactory
{
    public function create(ExportFormat $format): ExporterInterface
    {
        return match ($format) {
            ExportFormat::CSV => new CsvExporter(),
            ExportFormat::JSON => new JsonExporter(),
            ExportFormat::SQL => new SqlExporter(),
        };
    }
}
