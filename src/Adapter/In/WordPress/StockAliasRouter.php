<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Adapter\In\WordPress;

use Pollora\HiddenLogin\Application\Service\ClassifyStockAlias;
use Pollora\HiddenLogin\Domain\Model\DefaultEndpoint;
use Pollora\HiddenLogin\Port\Out\HookRegistrarPort;
use Pollora\HiddenLogin\Port\Out\RequestContextPort;
use WP_Rewrite;

/**
 * Stops core's convenience redirects from handing out the secret slug.
 *
 * `wp_redirect_admin_locations()` runs on `template_redirect` at priority 1000
 * and, on a request that already resolved to a 404, maps a short list of paths
 * onto the real thing:
 *
 * ```
 * /wp-admin, /dashboard, /admin  ->  admin_url()
 * /wp-login.php, /login.php, /login, site_url('login')  ->  wp_login_url()
 * ```
 *
 * The second branch is the problem. `wp_login_url()` goes through the filter
 * this package installs, so it returns the secret slug — and core puts it in a
 * `Location` header for anyone who asks:
 *
 * ```
 * $ curl -sI https://example.com/wp-login.php
 * HTTP/2 302
 * location: https://example.com/<the-secret-slug>
 * ```
 *
 * One request, no authentication, and the protection is gone. The router that
 * guards the real `wp-login.php` never sees this one: on Bedrock the file lives
 * under `/wp/`, so `/wp-login.php` at the site root is not a script at all —
 * it is an ordinary front-end request that happens to 404.
 *
 * ## Why replace rather than remove
 *
 * Dropping core's callback outright would fix the leak and also silently retire
 * `/admin` and `/dashboard`, which are unrelated to the secret and which people
 * do type. This registers a stand-in at the same hook and priority that keeps
 * the admin branch verbatim and simply lets the login aliases stay the 404 they
 * already are — which is exactly what the package promises for `wp-login.php`.
 */
final class StockAliasRouter
{
    /**
     * The core callback being replaced.
     *
     * @var string
     */
    private const CORE_CALLBACK = 'wp_redirect_admin_locations';

    /**
     * Hook and priority core registers it on, which the stand-in reuses so the
     * ordering relative to other `template_redirect` consumers is unchanged.
     *
     * @var string
     */
    private const HOOK = 'template_redirect';

    /**
     * @var int
     */
    private const PRIORITY = 1000;

    /**
     * @param  ClassifyStockAlias  $classifier  Decides which entry point a path aliases.
     * @param  RequestContextPort  $context  Current request, as seen from the runtime.
     * @param  HookRegistrarPort  $hooks  Hook system of the host.
     */
    public function __construct(
        private readonly ClassifyStockAlias $classifier,
        private readonly RequestContextPort $context,
        private readonly HookRegistrarPort $hooks,
    ) {}

    /**
     * Swaps core's handler for the stand-in.
     */
    public function register(): void
    {
        $this->hooks->removeAction(self::HOOK, self::CORE_CALLBACK, self::PRIORITY);
        $this->hooks->addAction(self::HOOK, [$this, 'route'], self::PRIORITY, 0);
    }

    /**
     * Redirects the admin aliases, leaves the login aliases as a 404.
     *
     * @internal Hooked on `template_redirect`; not part of the public API.
     */
    public function route(): void
    {
        if (! $this->coreWouldHaveRedirected()) {
            return;
        }

        $endpoint = $this->classifier->classify(
            $this->context->requestUri(),
            $this->adminAliases(),
            $this->loginAliases(),
        );

        if ($endpoint !== DefaultEndpoint::Admin) {
            // Login alias, or nothing we know: the request keeps the 404 it
            // already had. Falling through is the whole fix.
            return;
        }

        wp_safe_redirect(admin_url());
        exit;
    }

    /**
     * Reproduces core's own precondition.
     *
     * The redirects only ever fire on a request that already resolved to a 404
     * under pretty permalinks, which is what keeps a real page at `/login` from
     * being hijacked. The stand-in has to honour the same condition or it would
     * start redirecting requests core left alone.
     */
    private function coreWouldHaveRedirected(): bool
    {
        $rewrite = $GLOBALS['wp_rewrite'] ?? null;

        return is_404()
            && $rewrite instanceof WP_Rewrite
            && $rewrite->using_permalinks();
    }

    /**
     * Paths core maps to the admin URL, in core's own order.
     *
     * @return list<string>
     */
    private function adminAliases(): array
    {
        return [
            home_url('wp-admin', 'relative'),
            home_url('dashboard', 'relative'),
            home_url('admin', 'relative'),
            site_url('dashboard', 'relative'),
            site_url('admin', 'relative'),
        ];
    }

    /**
     * Paths core maps to the login URL — the ones that must not redirect.
     *
     * @return list<string>
     */
    private function loginAliases(): array
    {
        return [
            home_url('wp-login.php', 'relative'),
            home_url('login.php', 'relative'),
            home_url('login', 'relative'),
            site_url('login', 'relative'),
        ];
    }
}
