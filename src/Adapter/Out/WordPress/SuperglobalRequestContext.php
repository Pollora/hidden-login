<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Adapter\Out\WordPress;

use Pollora\HiddenLogin\Domain\Model\DefaultEndpoint;
use Pollora\HiddenLogin\Port\Out\HookRegistrarPort;
use Pollora\HiddenLogin\Port\Out\RequestContextPort;

/**
 * Reads the current request from PHP superglobals and WordPress globals.
 *
 * This adapter is the only place in the package that knows about `$_SERVER`,
 * `$pagenow` or `is_admin()`. Everything above it works on plain values.
 */
final class SuperglobalRequestContext implements RequestContextPort
{
    /**
     * Scripts under `wp-admin/` that must stay reachable without a session.
     *
     * `admin-ajax.php` and `admin-post.php` are the canonical entry points for
     * front-end AJAX and form handling in WordPress; plenty of plugins rely on
     * them for anonymous visitors. Blocking them would break the public site,
     * and they expose no administration UI, so hiding them buys nothing.
     *
     * @var list<string>
     */
    private const PUBLIC_ADMIN_SCRIPTS = [
        'admin-ajax.php',
        'admin-post.php',
    ];

    /**
     * @param  HookRegistrarPort  $hooks  Hook system of the host.
     */
    public function __construct(private readonly HookRegistrarPort $hooks) {}

    /**
     * {@inheritDoc}
     */
    public function requestUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return is_string($uri) ? $uri : '';
    }

    /**
     * {@inheritDoc}
     */
    public function homePath(): string
    {
        $path = parse_url(home_url('/'), PHP_URL_PATH);

        return is_string($path) ? trim($path, '/') : '';
    }

    /**
     * {@inheritDoc}
     */
    public function defaultEndpoint(): ?DefaultEndpoint
    {
        $page = $this->currentPage();

        if ($page === 'wp-login.php') {
            return DefaultEndpoint::Login;
        }

        if (! is_admin() || in_array($page, $this->publicAdminScripts(), true)) {
            return null;
        }

        return DefaultEndpoint::Admin;
    }

    /**
     * {@inheritDoc}
     */
    public function requestedAction(): string
    {
        $action = $_REQUEST['action'] ?? null;

        return is_string($action) && $action !== '' ? $action : 'login';
    }

    /**
     * {@inheritDoc}
     */
    public function isUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * {@inheritDoc}
     */
    public function isNonHttpContext(): bool
    {
        if (defined('WP_CLI') && WP_CLI) {
            return true;
        }

        if (function_exists('wp_doing_cron') && wp_doing_cron()) {
            return true;
        }

        return defined('DOING_CRON') && DOING_CRON;
    }

    /**
     * The script WordPress believes is handling the request.
     *
     * Mirrors `$GLOBALS['pagenow']`, which `wp-includes/vars.php` sets from
     * `$_SERVER['PHP_SELF']` well before `plugins_loaded` fires.
     */
    private function currentPage(): string
    {
        $page = $GLOBALS['pagenow'] ?? '';

        return is_string($page) ? $page : '';
    }

    /**
     * The `wp-admin/` scripts that stay publicly reachable.
     *
     * Filterable so that a host can open up an additional endpoint — a payment
     * callback landing on a custom admin script, for instance — without
     * forking the package.
     *
     * @return list<string>
     */
    private function publicAdminScripts(): array
    {
        /**
         * Filters the `wp-admin/` scripts that remain reachable to anonymous visitors.
         *
         * @param  list<string>  $scripts  Script file names, relative to `wp-admin/`.
         */
        $scripts = $this->hooks->applyFilters('hidden_login/public_admin_scripts', self::PUBLIC_ADMIN_SCRIPTS);

        return is_array($scripts) ? array_values(array_filter($scripts, 'is_string')) : self::PUBLIC_ADMIN_SCRIPTS;
    }
}
