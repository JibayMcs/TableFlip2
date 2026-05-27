<?php

declare(strict_types=1);

namespace App\Domain\Database\Exceptions;

use RuntimeException;

class RowEditingException extends RuntimeException
{
    public static function ambiguousRow(int $matches): self
    {
        return new self("Refusing to act: the WHERE clause matches {$matches} rows, expected exactly 1.");
    }

    public static function rowDisappeared(): self
    {
        return new self('Refusing to act: the targeted row no longer matches (it may have been changed by someone else).');
    }

    public static function tableNotEditable(string $reason): self
    {
        return new self("Table is not editable: {$reason}");
    }
}
