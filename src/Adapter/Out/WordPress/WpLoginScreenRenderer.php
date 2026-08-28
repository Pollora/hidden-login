<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Adapter\Out\WordPress;

use Pollora\HiddenLogin\Application\Service\RewriteLoginUrl;
use Pollora\HiddenLogin\Domain\Model\LoginSlug;
use Pollora\HiddenLogin\Port\Out\LoginScreenRendererPort;
use Pollora\HiddenLogin\Port\Out\RequestContextPort;

/**
 * Serves the stock `wp-login.php` screen from the secret URL.
 *
 * Rather than reimplementing the login screen — and with it the whole surface
 * of `login_form_*` hooks that two-factor, SSO and security plugins rely on —
 * this adapter includes the WordPress file itself. Every authentication flow
 * therefore keeps working exactly as upstream intends, at a different URL.
 */
final class WpLoginScreenRenderer implements LoginScreenRendererPort
{
    /**
     * @param  RequestContextPort  $context  Used to rebuild a canonical request URI.
     */
    public function __construct(private readonly RequestContextPort $context) {}

    /**
     * {@inheritDoc}
     *
     * Two things happen here, both before any plugin gets a chance to inspect
     * the request:
     *
     * 1. `$pagenow` is set to `wp-login.php`, so third-party code that keys off
     *    it — brute-force protection, two-factor providers — recognises the
     *    screen it is supposed to act on.
     *
     * 2. `REQUEST_URI` is rewritten to the *canonical* form of the slug, with no
     *    trailing slash. This is not cosmetic: the password reset screen scopes
     *    its `wp-resetpass-*` cookie on `current( explode( '?', REQUEST_URI ) )`,
     *    while the form it renders posts to the URL produced by
     *    {@see RewriteLoginUrl}. Should a
     *    visitor reach `/secret-slug/` while the form posts to `/secret-slug`,
     *    the cookie would not be sent back and the reset would fail with an
     *    "expired link" error that is close to impossible to diagnose.
     */
    public function prepare(LoginSlug $slug): void
    {
        $GLOBALS['pagenow'] = 'wp-login.php';

        $homePath = $this->context->homePath();
        $canonical = ($homePath === '' ? '' : '/'.$homePath).$slug->toPath();

        $query = parse_url($this->context->requestUri(), PHP_URL_QUERY);

        if (is_string($query) && $query !== '') {
            $canonical .= '?'.$query;
        }

        $_SERVER['REQUEST_URI'] = $canonical;
    }

    /**
     * {@inheritDoc}
     *
     * The `global` declarations are mandatory, not defensive. `wp-login.php` is
     * written to run at the top level of a script, where its variables are
     * global by definition; included from a method, they would become locals and
     * every consumer reaching for them through `global` would see `null`.
     * `login_header()` alone reads `$error`, `$interim_login` and `$action`, and
     * plugins commonly reach for `$errors` and `$user_login`.
     */
    public function render(): never
    {
        global $action,
        $customize_login,
        $default_actions,
        $error,
        $errors,
        $http_post,
        $interim_login,
        $login_link_separator,
        $reauth,
        $redirect_to,
        $requested_redirect_to,
        $rp_cookie,
        $rp_key,
        $rp_login,
        $rp_path,
        $secure,
        $secure_cookie,
        $user,
        $user_login;

        require_once ABSPATH.'wp-login.php';

        exit;
    }
}
