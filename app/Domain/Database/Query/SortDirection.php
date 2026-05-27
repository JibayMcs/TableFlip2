<?php

declare(strict_types=1);

namespace App\Domain\Database\Query;

enum SortDirection: string
{
    case ASC = 'asc';
    case DESC = 'desc';

    public function toggle(): self
    {
        return $this === self::ASC ? self::DESC : self::ASC;
    }
}
