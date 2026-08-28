<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin;

use Pollora\HiddenLogin\Adapter\In\WordPress\Cli\HiddenLoginCommand;
use Pollora\HiddenLogin\Adapter\In\WordPress\HiddenLoginRouter;
use Pollora\HiddenLogin\Adapter\In\WordPress\LoginUrlRewriter;
use Pollora\HiddenLogin\Adapter\In\WordPress\SlugCollisionNotice;
use Pollora\HiddenLogin\Adapter\Out\Pollora\PolloraHookRegistrar;
use Pollora\HiddenLogin\Adapter\Out\WordPress\EnvironmentFeatureToggle;
use Pollora\HiddenLogin\Adapter\Out\WordPress\EnvironmentSlugProvider;
use Pollora\HiddenLogin\Adapter\Out\WordPress\SuperglobalRequestContext;
use Pollora\HiddenLogin\Adapter\Out\WordPress\ThemeNotFoundResponder;
use Pollora\HiddenLogin\Adapter\Out\WordPress\WordPressHookRegistrar;
use Pollora\HiddenLogin\Adapter\Out\WordPress\WpLoginScreenRenderer;
use Pollora\HiddenLogin\Application\Service\GuardDefaultEndpoints;
use Pollora\HiddenLogin\Application\Service\MatchHiddenLoginRequest;
use Pollora\HiddenLogin\Application\Service\ResolveLoginSlug;
use Pollora\HiddenLogin\Application\Service\RewriteLoginUrl;
use Pollora\HiddenLogin\Domain\Exception\InvalidLoginSlugException;
use Pollora\HiddenLogin\Port\Out\FeatureTogglePort;
use Pollora\HiddenLogin\Port\Out\HookRegistrarPort;
use Pollora\HiddenLogin\Port\Out\SlugProviderPort;

/**
 * Composition root: wires the adapters to the use cases and registers the hooks.
 *
 * Nothing has to call this. Requiring the package is enough — {@see Bootstrap}
 * is registered through Composer's `autoload.files` and schedules the boot on
 * its own. The method stays public for hosts that want to control the moment, or
 * to inject their own adapters:
 *
 * ```php
 * \Pollora\HiddenLogin\HiddenLogin::boot(new MyOptionSlugProvider());
 * ```
 *
 * Two independent conditions keep the package out of the way:
 *
 * - `HIDDEN_LOGIN_ENABLED` set to a falsy value switches it off entirely.
 *   Enabled by default: an installation that pulled the package in has opted in.
 * - No slug configured leaves it dormant. That is a deliberate fail-open — a
 *   freshly provisioned environment, a restored dump or a missing `.env` entry
 *   must leave a site usable rather than lock everybody out of an installation
 *   nobody can reach a terminal on.
 */
final class HiddenLogin
{
    /**
     * Package version, exposed for host applications that report their stack.
     */
    public const VERSION = '1.0.0';

    /**
     * Guards against registering the hooks twice.
     *
     * The package can be reached both by the Composer bootstrap and by an
     * explicit call from a host, and duplicate filters would rewrite login URLs
     * twice over.
     */
    private static bool $booted = false;

    /**
     * Boots the package.
     *
     * @param  SlugProviderPort|null  $slugProvider  Where to read the slug from. Defaults to
     *                                               {@see EnvironmentSlugProvider}, which reads the
     *                                               `HIDDEN_LOGIN_SLUG` constant then the environment.
     * @param  FeatureTogglePort|null  $toggle  Where to read the kill switch from. Defaults to
     *                                          {@see EnvironmentFeatureToggle}.
     * @param  HookRegistrarPort|null  $hooks  Hook system to register against. Defaults to
     *                                         Pollora's `Action`/`Filter` layer when it is usable,
     *                                         and to the plain WordPress plugin API otherwise.
     */
    public static function boot(
        ?SlugProviderPort $slugProvider = null,
        ?FeatureTogglePort $toggle = null,
        ?HookRegistrarPort $hooks = null,
    ): void {
        if (self::$booted) {
            return;
        }

        if (! ($toggle ?? new EnvironmentFeatureToggle)->state()->isEnabled()) {
            return;
        }

        self::$booted = true;

        $provider = $slugProvider ?? new EnvironmentSlugProvider;
        $registrar = $hooks ?? self::detectHookRegistrar();

        HiddenLoginCommand::register($provider);

        try {
            $slug = (new ResolveLoginSlug($provider))->resolve();
        } catch (InvalidLoginSlugException $exception) {
            self::reportMisconfiguration($exception, $registrar);

            return;
        }

        if ($slug === null) {
            return;
        }

        $context = new SuperglobalRequestContext($registrar);

        (new LoginUrlRewriter($slug, new RewriteLoginUrl, $registrar))->register();

        (new HiddenLoginRouter(
            $slug,
            $context,
            new MatchHiddenLoginRequest,
            new GuardDefaultEndpoints,
            new WpLoginScreenRenderer($context),
            new ThemeNotFoundResponder($context, $registrar),
            $registrar,
        ))->register();

        (new SlugCollisionNotice($slug, $registrar))->register();
    }

    /**
     * Picks the hook implementation that fits the host.
     *
     * On Pollora, hooks registered through the framework take part in its own
     * lifecycle; on anything else, the WordPress plugin API is the only option.
     */
    private static function detectHookRegistrar(): HookRegistrarPort
    {
        return PolloraHookRegistrar::isAvailable()
            ? new PolloraHookRegistrar
            : new WordPressHookRegistrar;
    }

    /**
     * Surfaces a configuration error without breaking the site.
     *
     * A rejected slug leaves WordPress in its stock configuration, which is a
     * safe but silent outcome — hence both a log line for the operator and an
     * admin notice for whoever ends up wondering why the protection is off.
     *
     * @param  InvalidLoginSlugException  $exception  The validation failure.
     * @param  HookRegistrarPort  $hooks  Hook system of the host.
     */
    private static function reportMisconfiguration(
        InvalidLoginSlugException $exception,
        HookRegistrarPort $hooks,
    ): void {
        error_log('[hidden-login] '.$exception->getMessage());

        $hooks->addAction('admin_notices', static function () use ($exception): void {
            if (! current_user_can('manage_options')) {
                return;
            }

            printf(
                '<div class="notice notice-error"><p><strong>hidden-login</strong> — %s</p></div>',
                esc_html($exception->getMessage())
            );
        }, 10, 0);
    }
}
