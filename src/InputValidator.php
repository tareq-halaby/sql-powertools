<?php

/**
 * InputValidator
 *
 * Provides centralized input validation and sanitization for SQL PowerTools.
 * This class helps prevent SQL injection and other security vulnerabilities.
 */
class InputValidator
{
    /**
     * Validate and sanitize a database name.
     *
     * @param string $name The database name to validate
     * @return string|false Returns the sanitized name or false if invalid
     */
    public static function validateDatabaseName(string $name): string|false
    {
        $name = trim($name);

        if (empty($name)) {
            return false;
        }

        // Only allow alphanumeric characters, underscores, and hyphens
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            return false;
        }

        // Prevent path traversal
        if (str_contains($name, '..') || str_contains($name, '/')) {
            return false;
        }

        // Enforce max length
        if (strlen($name) > 64) {
            return false;
        }

        return $name;
    }

    /**
     * Validate row count for sampling.
     *
     * @param mixed $count The row count to validate
     * @param int $max Maximum allowed rows
     * @param int $default Default value if invalid
     * @return int Validated row count
     */
    public static function validateRowCount(mixed $count, int $max = 10000, int $default = 100): int
    {
        $count = filter_var($count, FILTER_VALIDATE_INT);

        if ($count === false || $count < 1) {
            return $default;
        }

        return min($count, $max);
    }

    /**
     * Validate a boolean flag from user input.
     *
     * @param mixed $value The value to validate
     * @return bool
     */
    public static function validateBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Sanitize output for safe display in HTML.
     *
     * @param string $output The string to sanitize
     * @return string HTML-escaped string
     */
    public static function sanitizeOutput(string $output): string
    {
        return htmlspecialchars($output, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
