<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Adapter\Out\WordPress;

use Pollora\HiddenLogin\Port\Out\HookRegistrarPort;
use Pollora\HiddenLogin\Port\Out\NotFoundResponderPort;
use Pollora\HiddenLogin\Port\Out\RequestContextPort;

/**
 * Answers blocked requests with the theme's own 404 page.
 *
 * Serving the real 404 template — rather than a bare status line — is what
 * makes `wp-login.php` and `wp-admin/` indistinguishable from any other URL
 * that was never there. A distinctive error page would confirm to a scanner
 * that WordPress is installed and that the login has merely been moved.
 */
final class ThemeNotFoundResponder implements NotFoundResponderPort
{
    /**
     * @param  RequestContextPort  $context  Provides the path the request came in on.
     * @param  HookRegistrarPort  $hooks  Hook system of the host.
     */
    public function __construct(
        private readonly RequestContextPort $context,
        private readonly HookRegistrarPort $hooks,
    ) {}

    /**
     * {@inheritDoc}
     *
     * The path is kept as it came in, minus its query string. Substituting a
     * decoy path would be simpler, but WordPress echoes the current URL into the
     * rendered page — a login link's `redirect_to`, for instance — and a decoy
     * would show up there, handing a scanner the very signal this package
     * exists to withhold. Keeping the real path makes the response
     * byte-identical to the 404 the site would serve if the endpoint had never
     * existed.
     *
     * The query and the body are discarded. The request is going to die anyway,
     * but `init` still runs between this call and {@see self::respond()}, and
     * there is no reason to let a plugin act on the parameters of a request that
     * has just been refused — nor to reflect them into the rendered page.
     */
    public function prepare(): void
    {
        $path = parse_url($this->context->requestUri(), PHP_URL_PATH);

        $_GET = [];
        $_POST = [];
        $_REQUEST = [];

        $_SERVER['REQUEST_URI'] = is_string($path) && $path !== '' ? $path : '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $GLOBALS['pagenow'] = 'index.php';

        $this->disableAdminBar();

        // WordPress tries to guess a permalink for 404s, and could 301 the
        // blocked request onto a post whose slug happens to share a prefix with
        // wp-admin. A refusal must stay a refusal, so the canonical pass goes.
        $this->hooks->removeAction('template_redirect', 'redirect_canonical');
    }

    /**
     * {@inheritDoc}
     */
    public function respond(): never
    {
        if (! headers_sent()) {
            nocache_headers();
        }

        /**
         * Filters whether the blocked request is answered with the theme's 404 template.
         *
         * Set to `false` to fall back to a minimal, theme-less 404 document. The
         * escape hatch exists because blocked `wp-admin/` requests are rendered
         * with `WP_ADMIN` already defined — the constant is set by the admin
         * bootstrap before WordPress is even loaded, so it cannot be undone —
         * and a theme or plugin that branches on `is_admin()` during rendering
         * may misbehave.
         *
         * @param  bool  $render  Whether to render the theme template. Default `true`.
         */
        if ($this->hooks->applyFilters('hidden_login/render_theme_404', true)) {
            $this->renderThemeTemplate();
        }

        $this->renderMinimalDocument();
    }

    /**
     * Prevents the admin bar from initialising or rendering.
     *
     * Mandatory whenever the blocked request came from `wp-admin/`, and harmless
     * otherwise. `is_admin_bar_showing()` returns `true` *unconditionally* when
     * `is_admin()` is true — it never even reaches the `show_admin_bar` filter —
     * so the bar would render on the 404 page. It then fataly dereferences
     * `get_current_screen()`, which is `null` because the administration
     * bootstrap is interrupted long before `set_current_screen()` runs.
     *
     * Unhooking the callbacks registered in `default-filters.php` is the only
     * lever that works here. They are already registered by the time this runs:
     * `wp-settings.php` loads the default filters well before `plugins_loaded`.
     */
    private function disableAdminBar(): void
    {
        $this->hooks->removeAction('template_redirect', '_wp_admin_bar_init', 0);
        $this->hooks->removeAction('admin_init', '_wp_admin_bar_init');
        $this->hooks->removeAction('wp_body_open', 'wp_admin_bar_render', 0);
        $this->hooks->removeAction('wp_footer', 'wp_admin_bar_render', 1000);
        $this->hooks->removeAction('in_admin_header', 'wp_admin_bar_render', 0);

        // Belt and braces for the front-end path, where the filter *is* honoured.
        $this->hooks->addFilter('show_admin_bar', static fn (): bool => false, PHP_INT_MAX);
    }

    /**
     * Replays the front controller so the theme produces its 404 page.
     *
     * This is `wp-blog-header.php` minus the bootstrap, which has already
     * happened: run the main query, then hand over to the template loader. Both
     * `$wp` and `$wp_query` exist by the time this runs, since the caller defers
     * it to the very end of `wp_loaded`.
     *
     * The 404 flag is forced rather than inferred. Paths under `wp-admin/` and
     * `wp-login.php` cannot match content on a sane installation, but the
     * response this method produces is a refusal, and a refusal must not depend
     * on how the query happened to resolve.
     */
    private function renderThemeTemplate(): never
    {
        // wp-login.php and wp-admin/ do not define this constant, and the
        // template loader is a no-op without it.
        if (! defined('WP_USE_THEMES')) {
            define('WP_USE_THEMES', true);
        }

        wp();

        if (isset($GLOBALS['wp_query']) && $GLOBALS['wp_query'] instanceof \WP_Query) {
            $GLOBALS['wp_query']->set_404();
        }

        status_header(404);

        require_once ABSPATH.WPINC.'/template-loader.php';

        exit;
    }

    /**
     * Emits a minimal 404 document, used when theme rendering is opted out of.
     */
    private function renderMinimalDocument(): never
    {
        status_header(404);

        if (! headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        echo "<!doctype html>\n";
        echo "<html><head><meta charset=\"utf-8\"><title>404</title></head>\n";
        echo "<body><h1>404</h1></body></html>\n";

        exit;
    }
}
