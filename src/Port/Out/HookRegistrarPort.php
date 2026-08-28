<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Port\Out;

/**
 * Registers, removes and applies hooks.
 *
 * The package never calls `add_action()` or `apply_filters()` directly: the hook
 * system is a detail of the host, and putting it behind a port is what lets the
 * same code run on a bare WordPress installation and on Pollora, where hooks go
 * through the framework's own `Action` and `Filter` layer.
 *
 * The one deliberate exception is the Composer bootstrap, which has to schedule
 * itself before either implementation can exist.
 */
interface HookRegistrarPort
{
    /**
     * Registers a callback on an action.
     *
     * @param  string  $hook  Hook name.
     * @param  callable  $callback  Callback to run.
     * @param  int  $priority  Lower runs earlier.
     * @param  int  $acceptedArgs  Number of arguments the callback accepts.
     */
    public function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void;

    /**
     * Registers a callback on a filter.
     *
     * @param  string  $hook  Hook name.
     * @param  callable  $callback  Callback returning the filtered value.
     * @param  int  $priority  Lower runs earlier.
     * @param  int  $acceptedArgs  Number of arguments the callback accepts.
     */
    public function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void;

    /**
     * Unregisters a callback from an action.
     *
     * The callback is accepted as a plain string as well, because the callbacks
     * that have to be removed here are WordPress core functions referenced by
     * name in `default-filters.php`.
     *
     * @param  string  $hook  Hook name.
     * @param  callable|string  $callback  Callback to remove.
     * @param  int  $priority  Priority it was registered with; must match.
     */
    public function removeAction(string $hook, callable|string $callback, int $priority = 10): void;

    /**
     * Applies a filter and returns the filtered value.
     *
     * @param  string  $hook  Hook name.
     * @param  mixed  $value  Value to filter.
     * @param  mixed  ...$args  Extra arguments passed to the callbacks.
     */
    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed;
}
