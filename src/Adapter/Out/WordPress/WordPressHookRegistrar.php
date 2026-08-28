<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Adapter\Out\WordPress;

use Pollora\HiddenLogin\Port\Out\HookRegistrarPort;

/**
 * Hook registrar backed by the WordPress plugin API.
 *
 * The default implementation, and the only one that works on a bare
 * installation — Bedrock included.
 */
final class WordPressHookRegistrar implements HookRegistrarPort
{
    /**
     * {@inheritDoc}
     */
    public function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        add_action($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * {@inheritDoc}
     */
    public function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        add_filter($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * {@inheritDoc}
     */
    public function removeAction(string $hook, callable|string $callback, int $priority = 10): void
    {
        remove_action($hook, $callback, $priority);
    }

    /**
     * {@inheritDoc}
     */
    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return apply_filters($hook, $value, ...$args);
    }
}
