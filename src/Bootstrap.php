<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin;

use Pollora\HiddenLogin\Port\Out\HookRegistrarPort;

/**
 * Self-registration entry point, invoked by Composer's `autoload.files`.
 *
 * Requiring the package is all it takes: no must-use plugin, no service
 * provider, no call in the host's configuration.
 *
 * This is the one class allowed to touch the hook system directly rather than
 * going through {@see HookRegistrarPort}, for the
 * simple reason that it runs before any implementation of that port could
 * work — Composer's autoloader is required from `wp-config.php`, several steps
 * before WordPress defines `add_action()`.
 */
final class Bootstrap
{
    /**
     * Hook the boot is deferred to.
     *
     * The first action `wp-settings.php` fires, and it fires late enough for the
     * configuration constants to exist and for must-use plugins to have had
     * their say on the package's own filters, yet early enough that nothing has
     * produced a login URL.
     */
    private const BOOT_HOOK = 'muplugins_loaded';

    /**
     * Schedules the boot, whatever state the host is in.
     *
     * Three cases, in the order they are likely to happen:
     *
     * 1. WordPress is not loaded yet — the usual case, since Composer's
     *    autoloader is required from `wp-config.php`. The callback is added to
     *    the pre-initialised hook array, which `wp-settings.php` normalises into
     *    real `WP_Hook` objects on the way up.
     * 2. WordPress is loaded but has not reached the hook yet — a host requiring
     *    the package later, from a plugin or a service provider.
     * 3. The hook has already fired — boot immediately, or the package would
     *    silently never register.
     */
    public static function schedule(): void
    {
        if (! function_exists('add_action')) {
            self::preregister();

            return;
        }

        if (function_exists('did_action') && did_action(self::BOOT_HOOK)) {
            HiddenLogin::boot();

            return;
        }

        add_action(self::BOOT_HOOK, [HiddenLogin::class, 'boot'], 0, 0);
    }

    /**
     * Seeds the callback into WordPress' pre-initialised hook array.
     *
     * `$wp_filter` may legitimately be populated before WordPress loads:
     * `wp-settings.php` passes whatever it finds to
     * `WP_Hook::build_preinitialized_hooks()`, which expects the raw
     * `[$hook][$priority][] = ['function' => …, 'accepted_args' => …]` shape used
     * here.
     */
    private static function preregister(): void
    {
        if (! isset($GLOBALS['wp_filter']) || ! is_array($GLOBALS['wp_filter'])) {
            $GLOBALS['wp_filter'] = [];
        }

        $GLOBALS['wp_filter'][self::BOOT_HOOK][0][] = [
            'function' => [HiddenLogin::class, 'boot'],
            'accepted_args' => 0,
        ];
    }
}
