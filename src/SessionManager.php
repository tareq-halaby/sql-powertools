<?php

/**
 * SessionManager
 *
 * Handles secure session management for SQL PowerTools.
 * Wraps PHP sessions with security best practices.
 */
class SessionManager
{
    private static bool $started = false;

    /**
     * Start a secure session with hardened settings.
     */
    public static function start(): void
    {
        if (self::$started) {
            return;
        }

        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '3600');

        session_start();
        self::$started = true;
    }

    /**
     * Set a session value.
     */
    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if a session key exists.
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session key.
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Destroy the entire session.
     */
    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
        self::$started = false;
    }

    /**
     * Regenerate session ID to prevent session fixation.
     */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }
}
