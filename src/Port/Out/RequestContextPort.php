<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Port\Out;

use Pollora\HiddenLogin\Domain\Model\DefaultEndpoint;

/**
 * Read-only view of the current request, as seen from the host runtime.
 *
 * Everything the routing decision depends on is funnelled through this port so
 * that the Application layer never touches a superglobal, a WordPress global or
 * a WordPress function — and can be unit tested with a plain fake.
 */
interface RequestContextPort
{
    /**
     * The raw request URI, query string included.
     */
    public function requestUri(): string;

    /**
     * The path component of the site's home URL.
     *
     * Empty string on a root installation; something like `blog` when WordPress
     * is served from a subdirectory.
     */
    public function homePath(): string;

    /**
     * Which stock WordPress entry point the current request targets, if any.
     *
     * Returns `null` for every request that is neither `wp-login.php` nor an
     * administration screen — that is, for the overwhelming majority of
     * front-end traffic.
     */
    public function defaultEndpoint(): ?DefaultEndpoint;

    /**
     * The `action` requested on `wp-login.php`, defaulting to `login`.
     *
     * Mirrors the way `wp-login.php` itself computes `$action`, so that an
     * allow list configured by the host lines up with what the script would do.
     */
    public function requestedAction(): string;

    /**
     * Whether a user is authenticated for this request.
     *
     * Safe to call from `plugins_loaded`: `pluggable.php` is loaded earlier in
     * `wp-settings.php`.
     */
    public function isUserLoggedIn(): bool;

    /**
     * Whether the request runs outside of an HTTP context (WP-CLI, WP-Cron).
     *
     * Those contexts must never be intercepted: WP-CLI is the escape hatch used
     * to recover a misconfigured slug, and cron has no login screen to serve.
     */
    public function isNonHttpContext(): bool;
}
