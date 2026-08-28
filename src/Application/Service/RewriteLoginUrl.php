<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Application\Service;

use Pollora\HiddenLogin\Domain\Model\LoginSlug;

/**
 * Rewrites any URL pointing at `wp-login.php` into the secret login URL.
 *
 * This single transformation covers every login-adjacent URL WordPress
 * produces, because they are all built on top of `site_url()` or
 * `network_site_url()` with the `login` / `login_post` scheme:
 *
 * - `wp_login_url()`, `wp_logout_url()`, `wp_lostpassword_url()`, `wp_registration_url()`
 * - the `action=rp` link sent in the password reset email
 * - the `action=confirmaction` link sent for privacy requests
 * - the form action of password-protected posts (`action=postpass`)
 * - the iframe source of the expired-session modal (`interim-login=1`)
 *
 * It also has to be applied to redirect locations, because `wp-login.php`
 * redirects to *relative* URLs in several places — for instance
 * `wp_safe_redirect( 'wp-login.php?checkemail=confirm' )` after a password
 * reset request. Left alone, the browser would resolve that against the secret
 * slug and land on `/secret-slug/wp-login.php`.
 *
 * The result is always absolute, which sidesteps relative resolution entirely.
 */
final class RewriteLoginUrl
{
    /**
     * The stock login script, as it appears in every URL WordPress builds.
     */
    private const LOGIN_SCRIPT = 'wp-login.php';

    /**
     * Whether the given URL would be affected by {@see self::rewrite()}.
     *
     * Exposed so that callers can skip the — comparatively expensive — parsing
     * on the vast majority of URLs that have nothing to do with the login.
     *
     * @param  string  $url  Absolute or relative URL.
     */
    public function applies(string $url): bool
    {
        return str_contains($url, self::LOGIN_SCRIPT);
    }

    /**
     * Rewrites a `wp-login.php` URL into the secret login URL.
     *
     * The query string and the fragment are preserved verbatim: they carry the
     * action, the reset key, the redirection target and the nonces, all of which
     * `wp-login.php` still needs once it is served from the new path.
     *
     * @param  string  $url  Absolute or relative URL, possibly unrelated to the login.
     * @param  string  $baseUrl  Absolute site root to build the result on, e.g. `https://example.com`.
     *                           The caller is responsible for picking the right scheme.
     * @param  LoginSlug  $slug  The configured login slug.
     * @return string The rewritten absolute URL, or `$url` untouched when it does not
     *                point at the login script.
     */
    public function rewrite(string $url, string $baseUrl, LoginSlug $slug): string
    {
        if (! $this->applies($url)) {
            return $url;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        $rewritten = rtrim($baseUrl, '/').$slug->toPath();

        if (isset($parts['query']) && $parts['query'] !== '') {
            $rewritten .= '?'.$parts['query'];
        }

        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $rewritten .= '#'.$parts['fragment'];
        }

        return $rewritten;
    }
}
