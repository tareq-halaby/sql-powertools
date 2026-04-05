<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * InputValidator - Centralized Input Validation and Sanitization
 *
 * Provides centralized input validation and sanitization for SQL PowerTools.
 * This class helps prevent SQL injection and other security vulnerabilities.
 *
 * Enhancements in v2.0.0:
 * - Added declare(strict_types=1) and namespace
 * - Fixed validateBool() to correctly return bool (not null)
 * - Added validateEmail() and validateUrl() helpers
 * - Added validateTableName() alias for validateDatabaseName()
 * - Added validatePositiveInt() for positive integer validation
 *
 * @package SqlPowertools
 * @version 2.0.0
 */
class InputValidator
{
    /**
     * Validate and sanitize a database or table name.
     *
     * @param string $name The name to validate
     * @return string|false The sanitized name or false if invalid
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
     * Alias for validateDatabaseName() for table-specific context.
     *
     * @param string $name
     * @return string|false
     */
    public static function validateTableName(string $name): string|false
    {
        return self::validateDatabaseName($name);
    }

    /**
     * Validate row count for sampling.
     *
     * @param mixed $count   The row count to validate
     * @param int   $max     Maximum allowed rows
     * @param int   $default Default value if invalid
     * @return int           Validated row count
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
     * Validate a positive integer value.
     *
     * @param mixed $value
     * @param int   $default
     * @return int
     */
    public static function validatePositiveInt(mixed $value, int $default = 0): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        return ($int !== false && $int > 0) ? $int : $default;
    }

    /**
     * Validate a boolean flag from user input.
     *
     * @param mixed $value The value to validate
     * @return bool
     */
    public static function validateBool(mixed $value): bool
    {
        $result = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $result ?? false;
    }

    /**
     * Validate an email address.
     *
     * @param mixed $email
     * @return string|false Sanitized email or false if invalid
     */
    public static function validateEmail(mixed $email): string|false
    {
        $sanitized = filter_var((string) $email, FILTER_SANITIZE_EMAIL);
        return filter_var($sanitized, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Validate a URL.
     *
     * @param mixed $url
     * @return string|false Sanitized URL or false if invalid
     */
    public static function validateUrl(mixed $url): string|false
    {
        $sanitized = filter_var((string) $url, FILTER_SANITIZE_URL);
        return filter_var($sanitized, FILTER_VALIDATE_URL);
    }

    /**
     * Sanitize output for safe display in HTML.
     *
     * @param string $output The string to sanitize
     * @return string        HTML-escaped string
     */
    public static function sanitizeOutput(string $output): string
    {
        return htmlspecialchars($output, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
