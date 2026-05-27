<?php

declare(strict_types=1);

namespace App\Domain\Database\ValueObjects;

enum ColumnType: string
{
    case STRING = 'string';
    case TEXT = 'text';
    case INTEGER = 'integer';
    case DECIMAL = 'decimal';
    case FLOAT = 'float';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case TIME = 'time';
    case TIMESTAMP = 'timestamp';
    case BINARY = 'binary';
    case JSON = 'json';
    case UUID = 'uuid';
    case ENUM = 'enum';
    case ARRAY = 'array';
    case OTHER = 'other';
}
