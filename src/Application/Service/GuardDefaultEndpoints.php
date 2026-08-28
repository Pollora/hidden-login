<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Application\Service;

use Pollora\HiddenLogin\Domain\Model\DefaultEndpoint;

/**
 * Decides whether a request on a stock WordPress entry point must be answered
 * with a 404.
 *
 * The rules are deliberately expressed here, away from any WordPress call, so
 * that the security-critical decision is a pure function of four inputs and can
 * be exhaustively tested.
 */
final class GuardDefaultEndpoints
{
    /**
     * @param  DefaultEndpoint  $endpoint  The entry point the request targets.
     * @param  bool  $isUserLoggedIn  Whether the visitor is authenticated.
     * @param  string  $requestedAction  The `action` query argument, defaulting to `login`.
     * @param  list<string>  $allowedLoginActions  Actions still tolerated on `wp-login.php`,
     *                                             for third-party integrations that post to it
     *                                             with a hard-coded URL. Empty by default.
     * @return bool `true` when the request must be turned into a 404.
     */
    public function shouldBlock(
        DefaultEndpoint $endpoint,
        bool $isUserLoggedIn,
        string $requestedAction,
        array $allowedLoginActions = [],
    ): bool {
        return match ($endpoint) {
            // wp-login.php is hidden for everybody, authenticated or not: every
            // legitimate flow — logging out, resetting a password, confirming a
            // privacy request — is reachable through the secret slug, because
            // WordPress builds all of those URLs through site_url()/network_site_url().
            DefaultEndpoint::Login => ! in_array($requestedAction, $allowedLoginActions, true),

            // wp-admin only needs to be hidden from anonymous visitors. Blocking
            // it for authenticated users would make the site unusable, and there
            // is nothing to hide from someone who already holds a session.
            DefaultEndpoint::Admin => ! $isUserLoggedIn,
        };
    }
}
