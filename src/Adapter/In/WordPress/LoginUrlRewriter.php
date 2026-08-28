<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Adapter\In\WordPress;

use Pollora\HiddenLogin\Application\Service\RewriteLoginUrl;
use Pollora\HiddenLogin\Domain\Model\LoginSlug;
use Pollora\HiddenLogin\Port\Out\HookRegistrarPort;

/**
 * Points every login URL WordPress produces at the secret slug.
 *
 * Three filters are enough to cover the whole surface:
 *
 * - `site_url` and `network_site_url` catch URL *construction*. Every helper —
 *   `wp_login_url()`, `wp_logout_url()`, `wp_lostpassword_url()`,
 *   `wp_registration_url()` — as well as the reset-password and
 *   new-user emails, the password-protected post form and the expired-session
 *   modal, go through one of them with the `login` or `login_post` scheme.
 *
 * - `wp_redirect` catches URL *consumption*. `wp-login.php` redirects to
 *   relative locations in several branches, `wp_safe_redirect( 'wp-login.php?checkemail=confirm' )`
 *   after a password reset request being the one users hit first. A relative
 *   location would be resolved by the browser against the secret slug and yield
 *   `/secret-slug/wp-login.php`, which the router does not serve.
 */
final class LoginUrlRewriter
{
    /**
     * Guards against re-entering the filters while resolving the home URL.
     *
     * A plugin filtering `home_url` and calling `site_url()` from there would
     * otherwise bounce between the two until PHP runs out of stack.
     */
    private bool $resolvingBaseUrl = false;

    /**
     * @param  LoginSlug  $slug  The configured login slug.
     * @param  RewriteLoginUrl  $rewriter  Pure URL transformation.
     * @param  HookRegistrarPort  $hooks  Hook system of the host.
     */
    public function __construct(
        private readonly LoginSlug $slug,
        private readonly RewriteLoginUrl $rewriter,
        private readonly HookRegistrarPort $hooks,
    ) {}

    /**
     * Registers the URL filters.
     */
    public function register(): void
    {
        $this->hooks->addFilter('site_url', [$this, 'filterSiteUrl'], 10, 4);
        $this->hooks->addFilter('network_site_url', [$this, 'filterNetworkSiteUrl'], 10, 3);
        $this->hooks->addFilter('wp_redirect', [$this, 'filterRedirect'], 10, 1);
    }

    /**
     * Rewrites `site_url()` results that point at the login script.
     *
     * @param  string  $url  The URL WordPress built.
     * @param  string  $path  The path that was requested, query string included.
     * @param  string|null  $scheme  The requested scheme (`login`, `login_post`, `rpc`, …).
     * @param  int|null  $blogId  Site ID on multisite; unused, part of the filter signature.
     *
     * @internal Hooked on `site_url`; not part of the public API.
     */
    public function filterSiteUrl(string $url, string $path, ?string $scheme = null, ?int $blogId = null): string
    {
        unset($path, $blogId);

        if (! $this->rewriter->applies($url)) {
            return $url;
        }

        return $this->rewriter->rewrite($url, $this->baseUrl($scheme, false), $this->slug);
    }

    /**
     * Rewrites `network_site_url()` results that point at the login script.
     *
     * This is the filter that fixes the password reset email, which is built
     * with `network_site_url( "wp-login.php?action=rp&key=…", 'login' )`.
     *
     * @param  string  $url  The URL WordPress built.
     * @param  string  $path  The path that was requested, query string included.
     * @param  string|null  $scheme  The requested scheme.
     *
     * @internal Hooked on `network_site_url`; not part of the public API.
     */
    public function filterNetworkSiteUrl(string $url, string $path, ?string $scheme = null): string
    {
        unset($path);

        if (! $this->rewriter->applies($url)) {
            return $url;
        }

        return $this->rewriter->rewrite($url, $this->baseUrl($scheme, true), $this->slug);
    }

    /**
     * Rewrites redirect locations that point at the login script.
     *
     * @param  string  $location  The location WordPress is about to send.
     *
     * @internal Hooked on `wp_redirect`; not part of the public API.
     */
    public function filterRedirect(string $location): string
    {
        if (! $this->rewriter->applies($location)) {
            return $location;
        }

        return $this->rewriter->rewrite($location, $this->baseUrl(null, false), $this->slug);
    }

    /**
     * The absolute site root the rewritten URL is built on.
     *
     * The home URL is used rather than the site URL because on a Bedrock-style
     * installation the latter carries the `/wp` subdirectory the core lives in,
     * and the secret login must sit at the public root instead.
     *
     * `home_url()` resolves the `login` and `login_post` schemes through
     * `set_url_scheme()`, which honours `force_ssl_admin()` — so an HTTPS-only
     * administration keeps producing HTTPS login URLs.
     *
     * The result is deliberately *not* memoised: multilingual plugins filter
     * `home_url` per language, and caching the first answer would pin the login
     * URL to whichever language happened to be resolved first. The cost is
     * irrelevant because the callers bail out on `applies()` long before getting
     * here for anything that is not a login URL.
     *
     * Re-entrant calls fall back to the raw `home` option: resolving the home
     * URL runs third-party filters, and one of them calling `site_url()` back
     * would otherwise recurse until the stack is exhausted.
     *
     * @param  string|null  $scheme  Scheme requested by the caller, or `null` to let WordPress decide.
     * @param  bool  $network  Whether the URL is being built for the network rather than the site.
     */
    private function baseUrl(?string $scheme, bool $network): string
    {
        if ($this->resolvingBaseUrl) {
            return rtrim((string) get_option('home'), '/');
        }

        $this->resolvingBaseUrl = true;

        try {
            $base = $network && is_multisite()
                ? network_home_url('/', $scheme)
                : home_url('/', $scheme);
        } finally {
            $this->resolvingBaseUrl = false;
        }

        return rtrim($base, '/');
    }
}
