<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Adapter\Out\Pollora;

use Illuminate\Support\Facades\Facade;
use Pollora\HiddenLogin\Port\Out\HookRegistrarPort;
use Pollora\Support\Facades\Action;
use Pollora\Support\Facades\Filter;

/**
 * Hook registrar backed by Pollora's `Action` and `Filter` layer.
 *
 * On a Pollora application, hooks registered through the framework take part in
 * its own lifecycle — container resolution, discovery, and the event bridge that
 * turns WordPress hooks into typed Laravel events. Going straight to
 * `add_action()` would bypass all of it, so the framework's facades are used
 * whenever they are available.
 *
 * The class is never loaded on a bare installation: {@see self::isAvailable()}
 * is checked before it is instantiated, and Composer only autoloads it at that
 * point.
 */
final class PolloraHookRegistrar implements HookRegistrarPort
{
    /**
     * Whether this registrar can be used in the current process.
     *
     * Both conditions matter. The facades may be autoloadable while the
     * container behind them is not yet set — Composer's autoloader runs long
     * before any application boots — and calling a facade in that state throws.
     */
    public static function isAvailable(): bool
    {
        if (! class_exists(Action::class) || ! class_exists(Filter::class)) {
            return false;
        }

        if (! class_exists(Facade::class)) {
            return false;
        }

        return Facade::getFacadeApplication() !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        Action::add($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * {@inheritDoc}
     */
    public function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        Filter::add($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * {@inheritDoc}
     */
    public function removeAction(string $hook, callable|string $callback, int $priority = 10): void
    {
        Action::remove($hook, $callback, $priority);
    }

    /**
     * {@inheritDoc}
     */
    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return Filter::apply($hook, $value, ...$args);
    }
}
