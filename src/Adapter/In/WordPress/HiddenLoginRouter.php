<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Adapter\In\WordPress;

use Pollora\HiddenLogin\Application\Service\GuardDefaultEndpoints;
use Pollora\HiddenLogin\Application\Service\MatchHiddenLoginRequest;
use Pollora\HiddenLogin\Domain\Model\LoginSlug;
use Pollora\HiddenLogin\Port\Out\HookRegistrarPort;
use Pollora\HiddenLogin\Port\Out\LoginScreenRendererPort;
use Pollora\HiddenLogin\Port\Out\NotFoundResponderPort;
use Pollora\HiddenLogin\Port\Out\RequestContextPort;

/**
 * Routes the request: secret slug to the login screen, stock endpoints to a 404.
 *
 * The work is split across two hooks, and the split is dictated by the order of
 * `wp-settings.php`:
 *
 * - `plugins_loaded` (priority 1) is the earliest point where `is_user_logged_in()`
 *   is usable — `pluggable.php` is loaded a few lines above — and it is still
 *   early enough to rewrite the request environment before any plugin reads it.
 *   Crucially it also runs *before* `wp-admin/admin.php` reaches `auth_redirect()`,
 *   which would otherwise redirect anonymous visitors to the login URL and hand
 *   them the secret slug.
 *
 * - `wp_loaded` (last priority) is where the response is produced. Rendering
 *   requires `$wp`, `$wp_query` and `$wp_rewrite`, which only exist after
 *   `plugins_loaded`, and running *last* is what reproduces the native ordering:
 *   `wp-blog-header.php` calls `wp()` once `wp_loaded` has fully completed, and
 *   `wp-login.php` renders once `wp-load.php` has returned. Anything earlier
 *   would skip the plugins that register on `wp_loaded` — on a Sage theme, for
 *   instance, Acorn would not have bound its `template_include` filter yet and
 *   the 404 template would fatal instead of rendering.
 *
 * Nothing is emitted in between, so a request that is going to be refused still
 * goes through `init` looking like an ordinary — and unremarkable — front-end hit.
 */
final class HiddenLoginRouter
{
    /**
     * @param  LoginSlug  $slug  The configured login slug.
     * @param  RequestContextPort  $context  Current request, as seen from the runtime.
     * @param  MatchHiddenLoginRequest  $matcher  Decides whether the request targets the slug.
     * @param  GuardDefaultEndpoints  $guard  Decides whether a stock endpoint must be refused.
     * @param  LoginScreenRendererPort  $renderer  Serves the login screen.
     * @param  NotFoundResponderPort  $notFound  Serves the 404.
     * @param  HookRegistrarPort  $hooks  Hook system of the host.
     */
    public function __construct(
        private readonly LoginSlug $slug,
        private readonly RequestContextPort $context,
        private readonly MatchHiddenLoginRequest $matcher,
        private readonly GuardDefaultEndpoints $guard,
        private readonly LoginScreenRendererPort $renderer,
        private readonly NotFoundResponderPort $notFound,
        private readonly HookRegistrarPort $hooks,
    ) {}

    /**
     * Registers the routing hooks.
     */
    public function register(): void
    {
        $this->hooks->addAction('plugins_loaded', [$this, 'route'], 1, 0);
    }

    /**
     * Decides what this request is, and schedules the matching response.
     *
     * @internal Hooked on `plugins_loaded`; not part of the public API.
     */
    public function route(): void
    {
        if ($this->context->isNonHttpContext()) {
            return;
        }

        if ($this->matcher->matches($this->context->requestUri(), $this->context->homePath(), $this->slug)) {
            $this->renderer->prepare($this->slug);
            $this->hooks->addAction('wp_loaded', [$this, 'renderLoginScreen'], PHP_INT_MAX, 0);

            return;
        }

        $endpoint = $this->context->defaultEndpoint();

        if ($endpoint === null) {
            return;
        }

        $blocked = $this->guard->shouldBlock(
            $endpoint,
            $this->context->isUserLoggedIn(),
            $this->context->requestedAction(),
            $this->allowedLoginActions(),
        );

        if (! $blocked) {
            return;
        }

        $this->notFound->prepare();
        $this->hooks->addAction('wp_loaded', [$this, 'renderNotFound'], PHP_INT_MAX, 0);
    }

    /**
     * Serves the login screen and terminates the request.
     *
     * @internal Hooked on `wp_loaded` last; not part of the public API.
     */
    public function renderLoginScreen(): never
    {
        $this->renderer->render();
    }

    /**
     * Serves the 404 and terminates the request.
     *
     * @internal Hooked on `wp_loaded`; not part of the public API.
     */
    public function renderNotFound(): never
    {
        $this->notFound->respond();
    }

    /**
     * Actions still tolerated on the stock `wp-login.php`.
     *
     * Empty by default: every WordPress flow builds its URLs through
     * `site_url()` or `network_site_url()` and therefore lands on the secret
     * slug. The filter is an escape hatch for third-party code that posts to
     * `wp-login.php` with a hard-coded URL and cannot be fixed otherwise.
     *
     * @return list<string>
     */
    private function allowedLoginActions(): array
    {
        /**
         * Filters the `wp-login.php` actions that are not turned into a 404.
         *
         * Each entry loosens the protection for the corresponding action, so
         * keep the list as short as the integration strictly requires.
         *
         * @param  list<string>  $actions  Action names, as read from `$_REQUEST['action']`.
         */
        $actions = $this->hooks->applyFilters('hidden_login/allowed_default_actions', []);

        return is_array($actions) ? array_values(array_filter($actions, 'is_string')) : [];
    }
}
