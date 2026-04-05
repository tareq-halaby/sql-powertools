<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * EventDispatcher - Query Lifecycle Event System
 *
 * Enables hooking into query lifecycle events (before/after execute,
 * on error) for logging, monitoring, and extending query behaviour.
 *
 * Enhancements in v2.0.0:
 * - Added listener priority support (higher runs first)
 * - Added once() to register one-time listeners
 * - Added removeListener() to remove a specific listener
 * - Added hasListeners() helper
 * - Fixed off() to return bool indicating whether listeners existed
 *
 * @package SqlPowertools
 * @version 2.0.0
 */
class EventDispatcher
{
    /**
     * Listeners keyed by event name.
     * Each entry: ['callback' => callable, 'priority' => int, 'once' => bool]
     *
     * @var array<string, array<int, array{callback: callable, priority: int, once: bool}>>
     */
    private array $listeners = [];

    /**
     * Register a listener for a given event.
     *
     * @param string   $event    Event name
     * @param callable $listener Callback to invoke
     * @param int      $priority Higher priority runs first (default 0)
     */
    public function on(string $event, callable $listener, int $priority = 0): void
    {
        $this->listeners[$event][] = [
            'callback' => $listener,
            'priority' => $priority,
            'once'     => false,
        ];
        $this->sortListeners($event);
    }

    /**
     * Register a listener that fires only once then is removed.
     *
     * @param string   $event
     * @param callable $listener
     * @param int      $priority
     */
    public function once(string $event, callable $listener, int $priority = 0): void
    {
        $this->listeners[$event][] = [
            'callback' => $listener,
            'priority' => $priority,
            'once'     => true,
        ];
        $this->sortListeners($event);
    }

    /**
     * Dispatch an event to all registered listeners.
     *
     * @param string  $event
     * @param mixed[] $payload
     */
    public function dispatch(string $event, array $payload = []): void
    {
        if (empty($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $index => $entry) {
            ($entry['callback'])($payload);

            if ($entry['once']) {
                unset($this->listeners[$event][$index]);
            }
        }

        // Re-index after possible removal
        if (isset($this->listeners[$event])) {
            $this->listeners[$event] = array_values($this->listeners[$event]);
        }
    }

    /**
     * Remove all listeners for a given event.
     *
     * @return bool True if listeners existed and were removed
     */
    public function off(string $event): bool
    {
        if (!isset($this->listeners[$event])) {
            return false;
        }
        unset($this->listeners[$event]);
        return true;
    }

    /**
     * Remove a specific listener from an event.
     *
     * @return bool True if the listener was found and removed
     */
    public function removeListener(string $event, callable $listener): bool
    {
        if (!isset($this->listeners[$event])) {
            return false;
        }

        foreach ($this->listeners[$event] as $index => $entry) {
            if ($entry['callback'] === $listener) {
                unset($this->listeners[$event][$index]);
                $this->listeners[$event] = array_values($this->listeners[$event]);
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether an event has any registered listeners.
     */
    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }

    /**
     * List all registered event names.
     *
     * @return string[]
     */
    public function events(): array
    {
        return array_keys($this->listeners);
    }

    /**
     * Sort listeners for an event by priority (descending).
     */
    private function sortListeners(string $event): void
    {
        usort(
            $this->listeners[$event],
            fn($a, $b) => $b['priority'] <=> $a['priority']
        );
    }
}
