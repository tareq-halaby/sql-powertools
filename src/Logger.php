<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * Logger - PSR-3 Inspired File Logger for SQL PowerTools
 *
 * A simple PSR-3 inspired logger for SQL PowerTools.
 * Logs operations for auditing and debugging purposes.
 *
 * Enhancements in v2.0.0:
 * - Added declare(strict_types=1) and namespace
 * - Added minimum log level filtering
 * - Fixed silent failure when file_put_contents() fails
 * - Fixed json_encode() failure on non-serializable context
 * - Added critical() and warning() PSR-3 aliases
 * - Added getLogFile() getter
 *
 * @package SqlPowertools
 * @version 2.0.0
 */
class Logger
{
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO  = 'INFO';
    public const LEVEL_WARN  = 'WARN';
    public const LEVEL_ERROR = 'ERROR';

    /** Log level order for filtering (higher = more severe) */
    private const LEVEL_ORDER = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO  => 1,
        self::LEVEL_WARN  => 2,
        self::LEVEL_ERROR => 3,
    ];

    private string $logFile;
    private bool   $enabled;
    private string $minLevel;

    /**
     * @param string $logFile  Path to the log file
     * @param bool   $enabled  Whether logging is active
     * @param string $minLevel Minimum level to log (DEBUG|INFO|WARN|ERROR)
     * @throws \InvalidArgumentException on unknown minLevel
     */
    public function __construct(
        string $logFile  = 'logs/app.log',
        bool   $enabled  = true,
        string $minLevel = self::LEVEL_DEBUG
    ) {
        if (!isset(self::LEVEL_ORDER[$minLevel])) {
            throw new \InvalidArgumentException(
                "Unknown log level '" . $minLevel . "'. Use DEBUG, INFO, WARN, or ERROR."
            );
        }
        $this->logFile  = $logFile;
        $this->enabled  = $enabled;
        $this->minLevel = $minLevel;
    }

    /** Return the log file path. */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /** Log a debug message. */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /** Log an info message. */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /** Log a warning message. */
    public function warn(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARN, $message, $context);
    }

    /** PSR-3 alias for warn(). */
    public function warning(string $message, array $context = []): void
    {
        $this->warn($message, $context);
    }

    /** Log an error message. */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /** PSR-3 alias for error() at critical severity. */
    public function critical(string $message, array $context = []): void
    {
        $this->error($message, $context);
    }

    /**
     * Core logging method.
     *
     * @throws \RuntimeException if the log line cannot be written
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        // Skip if below minimum level
        if ((self::LEVEL_ORDER[$level] ?? 0) < (self::LEVEL_ORDER[$this->minLevel] ?? 0)) {
            return;
        }

        $timestamp  = date('Y-m-d H:i:s');
        $contextStr = empty($context)
            ? ''
            : ' ' . (json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '[context encode failed]');
        $logLine    = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        $logDir = dirname($this->logFile);
        if (!is_dir($logDir) && !mkdir($logDir, 0750, true)) {
            throw new \RuntimeException('Failed to create log directory: ' . $logDir);
        }

        if (file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Failed to write log to: ' . $this->logFile);
        }
    }
}
