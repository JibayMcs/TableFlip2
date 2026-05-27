<?php

declare(strict_types=1);

namespace App\Application\Tables;

use App\Domain\Database\ValueObjects\ColumnDefinition;
use App\Domain\Database\ValueObjects\ColumnType;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Hybrid input validation:
 *  - Domain types and "required if NOT NULL" rules are enforced application-side
 *    so users get clear field-by-field error messages before the round-trip to
 *    the database.
 *  - Foreign keys, UNIQUE, CHECK constraints and other relational rules are
 *    intentionally left to the database — they are dialect-specific and the
 *    DB owns the source of truth anyway.
 */
class RowValidator
{
    /**
     * @param  list<ColumnDefinition>  $columns
     * @param  array<string, mixed>  $input  column => value
     * @param  bool  $isInsert  when true, omitted columns fall back to their default; when false
     *                          (update), omitted columns are simply not validated
     * @return array<string, mixed> the validated input (sanitised types)
     *
     * @throws ValidationException
     */
    public function validate(array $columns, array $input, bool $isInsert): array
    {
        $rules = [];
        $messages = [];

        foreach ($columns as $col) {
            $key = $col->name;
            $present = array_key_exists($key, $input);

            if ($isInsert) {
                if (! $col->nullable && $col->default === null && ! $col->autoIncrement) {
                    $rules[$key][] = 'required';
                } else {
                    $rules[$key][] = 'nullable';
                }
            } else {
                if (! $present) {
                    continue;
                }
                $rules[$key][] = $col->nullable ? 'nullable' : 'required';
            }

            foreach ($this->typeRules($col) as $rule) {
                $rules[$key][] = $rule;
            }
        }

        return Validator::make($input, $rules, $messages)->validate();
    }

    /**
     * @return list<string>
     */
    private function typeRules(ColumnDefinition $col): array
    {
        $rules = [];

        switch ($col->type) {
            case ColumnType::INTEGER:
                $rules[] = 'integer';
                break;
            case ColumnType::DECIMAL:
            case ColumnType::FLOAT:
                $rules[] = 'numeric';
                break;
            case ColumnType::BOOLEAN:
                $rules[] = 'boolean';
                break;
            case ColumnType::DATE:
                $rules[] = 'date';
                break;
            case ColumnType::DATETIME:
            case ColumnType::TIMESTAMP:
                $rules[] = 'date';
                break;
            case ColumnType::TIME:
                $rules[] = 'date_format:H:i,H:i:s';
                break;
            case ColumnType::ENUM:
                if ($col->enumValues) {
                    $rules[] = 'in:'.implode(',', $col->enumValues);
                }
                break;
            case ColumnType::JSON:
                $rules[] = 'json';
                break;
            case ColumnType::UUID:
                $rules[] = 'uuid';
                break;
            case ColumnType::STRING:
            case ColumnType::TEXT:
                if ($col->length) {
                    $rules[] = 'max:'.$col->length;
                }
                break;
            default:
                // For OTHER / BINARY / ARRAY: no app-side constraint, DB decides.
                break;
        }

        return $rules;
    }
}
