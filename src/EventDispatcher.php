<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * EventDispatcher - Query Lifecycle Event System
 *
 * Enables hooking into query lifecycle events (before/after execute,
 * on error) for logging, monitoring, and extending query behaviour.
 *
 * @package SqlPowertools
 * @version 1.2.0
 */
class EventDispatcher
{
    private array $listeners = [];

    /**
     * Register a listener for a given event.
     */
    public function on(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    /**
     * Dispatch an event to all registered listeners.
     */
    public function dispatch(string $event, array $payload = []): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($payload);
        }
    }

    /**
     * Remove all listeners for a given event.
     */
    public function off(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /**
     * List all registered event names.
     */
    public function events(): array
    {
        return array_keys($this->listeners);
    }
}
