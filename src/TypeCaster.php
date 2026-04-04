<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * TypeCaster - PDO Result Type Casting Utility
 *
 * Automatically casts column values from PDO string results
 * to their appropriate PHP scalar types based on a schema definition.
 *
 * @package SqlPowertools
 * @version 1.3.0
 */
class TypeCaster
{
    /**
     * Cast a row of PDO string results using a column type map.
     *
     * @param array $row    Raw PDO result row
     * @param array $schema Column type map ['col' => 'int'|'float'|'bool'|'string'|'json']
     */
    public function cast(array $row, array $schema): array
    {
        return array_map(
            fn($key, $value) => isset($schema[$key]) ? $this->castValue($value, $schema[$key]) : $value,
            array_keys($row),
            $row
        );
    }

    /**
     * Cast multiple rows using the same schema.
     */
    public function castAll(array $rows, array $schema): array
    {
        return array_map(fn($row) => $this->cast($row, $schema), $rows);
    }

    private function castValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
