<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * SessionManager - Secure PHP Session Management Utility
 *
 * Wraps PHP sessions with security best practices including
 * hardened cookie settings, session fixation prevention,
 * and flash message support.
 *
 * Enhancements in v2.0.0:
 * - Added declare(strict_types=1) and namespace
 * - Fixed start() to use session_status() instead of custom flag
 * - Added flash() and getFlash() for one-time messages
 * - Added getId() to retrieve current session ID
 * - Added pull() for read-and-remove pattern
 * - Added key validation in set()
 *
 * @package SqlPowertools
 * @version 2.0.0
 */
class SessionManager
{
    /**
     * Start a secure session with hardened settings.
     * Safe to call multiple times; will not restart an active session.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '3600');

        session_start();
    }

    /**
     * Get the current session ID.
     */
    public static function getId(): string
    {
        self::start();
        return session_id();
    }

    /**
     * Set a session value.
     *
     * @throws \InvalidArgumentException if the key is empty
     */
    public static function set(string $key, mixed $value): void
    {
        if ($key === '') {
            throw new \InvalidArgumentException('Session key must not be empty.');
        }
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session value.
     *
     * @param string $key
     * @param mixed  $default Value returned when the key is not set
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
    }

    /**
     * Get and remove a session value in one operation.
     *
     * @param string $key
     * @param mixed  $default Value returned when the key is not set
     * @return mixed
     */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::remove($key);
        return $value;
    }

    /**
     * Check if a session key exists.
     */
    public static function has(string $key): bool
    {
        self::start();
        return array_key_exists($key, $_SESSION);
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
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Regenerate session ID to prevent session fixation.
     */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    /**
     * Store a flash message (one-time, cleared after retrieval).
     *
     * @param string $type  Arbitrary category, e.g. 'success', 'error'
     * @param string $message
     */
    public static function flash(string $type, string $message): void
    {
        self::start();
        $_SESSION['__flash'][$type][] = $message;
    }

    /**
     * Retrieve and clear flash messages of a given type.
     *
     * @param string $type
     * @return string[]
     */
    public static function getFlash(string $type): array
    {
        self::start();
        $messages = $_SESSION['__flash'][$type] ?? [];
        unset($_SESSION['__flash'][$type]);
        return $messages;
    }
}
