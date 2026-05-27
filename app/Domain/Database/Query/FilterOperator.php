<?php

declare(strict_types=1);

namespace App\Domain\Database\Query;

enum FilterOperator: string
{
    case EQUALS = '=';
    case NOT_EQUALS = '!=';
    case GT = '>';
    case GTE = '>=';
    case LT = '<';
    case LTE = '<=';
    case CONTAINS = 'like';
    case NOT_CONTAINS = 'not_like';
    case STARTS_WITH = 'starts_with';
    case ENDS_WITH = 'ends_with';
    case IN = 'in';
    case IS_NULL = 'is_null';
    case IS_NOT_NULL = 'is_not_null';

    public function requiresValue(): bool
    {
        return ! in_array($this, [self::IS_NULL, self::IS_NOT_NULL], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::EQUALS => '=',
            self::NOT_EQUALS => '!=',
            self::GT => '>',
            self::GTE => '>=',
            self::LT => '<',
            self::LTE => '<=',
            self::CONTAINS => 'contains',
            self::NOT_CONTAINS => "doesn't contain",
            self::STARTS_WITH => 'starts with',
            self::ENDS_WITH => 'ends with',
            self::IN => 'in (csv)',
            self::IS_NULL => 'is null',
            self::IS_NOT_NULL => 'is not null',
        };
    }
}
