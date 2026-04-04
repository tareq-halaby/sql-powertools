<?php

/**
 * Logger
 *
 * A simple PSR-3 inspired logger for SQL PowerTools.
 * Logs operations for auditing and debugging purposes.
 */
class Logger
{
    private string $logFile;
    private bool $enabled;

    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO  = 'INFO';
    public const LEVEL_WARN  = 'WARN';
    public const LEVEL_ERROR = 'ERROR';

    public function __construct(string $logFile = 'logs/app.log', bool $enabled = true)
    {
        $this->logFile = $logFile;
        $this->enabled = $enabled;
    }

    /**
     * Log a debug message.
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log an info message.
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a warning message.
     */
    public function warn(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARN, $message, $context);
    }

    /**
     * Log an error message.
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Core logging method.
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        $logLine = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;

        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }

        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
}
