<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * TypeCaster - PDO Result Type Casting Utility
 *
 * Automatically casts column values from PDO string results
 * to their appropriate PHP scalar types based on a schema definition.
 *
 * Enhancements in v2.0.0:
 * - Added 'datetime' type that returns \DateTimeImmutable
 * - Fixed json type to throw on invalid JSON instead of returning null silently
 * - Fixed bool casting to handle string values 'true'/'false'/'yes'/'no'
 * - Added schema type validation to catch typos early
 * - Added castRow() alias for IDE-friendly naming
 *
 * @package SqlPowertools
 * @version 2.0.0
 */
class TypeCaster
{
    /** Supported type names */
    private const VALID_TYPES = ['int', 'float', 'bool', 'string', 'json', 'datetime'];

    /**
     * Cast a row of PDO string results using a column type map.
     *
     * @param array<string, mixed>         $row    Raw PDO result row
     * @param array<string, string>        $schema Column type map, e.g. ['col' => 'int']
     * @return array<string, mixed>
     * @throws \InvalidArgumentException  on unknown type in schema
     * @throws \RuntimeException          on invalid JSON value
     */
    public function cast(array $row, array $schema): array
    {
        $this->validateSchema($schema);

        return array_map(
            fn($key, $value) => isset($schema[$key]) ? $this->castValue($value, $schema[$key]) : $value,
            array_keys($row),
            $row
        );
    }

    /**
     * Alias for cast() with a more descriptive name.
     *
     * @param array<string, mixed>  $row
     * @param array<string, string> $schema
     * @return array<string, mixed>
     */
    public function castRow(array $row, array $schema): array
    {
        return $this->cast($row, $schema);
    }

    /**
     * Cast multiple rows using the same schema.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string>            $schema
     * @return array<int, array<string, mixed>>
     */
    public function castAll(array $rows, array $schema): array
    {
        $this->validateSchema($schema);
        return array_map(fn($row) => $this->cast($row, $schema), $rows);
    }

    /**
     * Validate that all types in the schema are known.
     *
     * @param array<string, string> $schema
     * @throws \InvalidArgumentException on unknown type
     */
    private function validateSchema(array $schema): void
    {
        foreach ($schema as $column => $type) {
            if (!in_array($type, self::VALID_TYPES, true)) {
                throw new \InvalidArgumentException(
                    "Unknown cast type '" . $type . "' for column '" . $column . "'. " .
                    "Supported types: " . implode(', ', self::VALID_TYPES) . "."
                );
            }
        }
    }

    /**
     * Cast a single value to the specified type.
     *
     * @throws \RuntimeException on invalid JSON
     */
    private function castValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int'      => (int) $value,
            'float'    => (float) $value,
            'bool'     => $this->castBool($value),
            'string'   => (string) $value,
            'json'     => $this->castJson($value),
            'datetime' => $this->castDatetime($value),
        };
    }

    /**
     * Cast a value to boolean, handling string representations.
     */
    private function castBool(mixed $value): bool
    {
        if (is_string($value)) {
            return !in_array(strtolower($value), ['0', 'false', 'no', 'off', ''], true);
        }
        return (bool) $value;
    }

    /**
     * Decode a JSON string, throwing on invalid JSON.
     *
     * @throws \RuntimeException
     */
    private function castJson(mixed $value): mixed
    {
        $decoded = json_decode((string) $value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Failed to decode JSON value: ' . json_last_error_msg()
            );
        }
        return $decoded;
    }

    /**
     * Parse a datetime string into a DateTimeImmutable instance.
     *
     * @throws \RuntimeException on invalid datetime string
     */
    private function castDatetime(mixed $value): \DateTimeImmutable
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $value);
        if ($dt === false) {
            // Try generic parsing as fallback
            try {
                return new \DateTimeImmutable((string) $value);
            } catch (\Exception $e) {
                throw new \RuntimeException(
                    "Cannot parse datetime value '" . $value . "': " . $e->getMessage()
                );
            }
        }
        return $dt;
    }
}
