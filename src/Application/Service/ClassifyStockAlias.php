<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Application\Service;

use Pollora\HiddenLogin\Domain\Model\DefaultEndpoint;
use Pollora\HiddenLogin\Domain\Model\RequestPath;
use Pollora\HiddenLogin\Port\Out\RequestContextPort;

/**
 * Recognises the convenience URLs WordPress maps onto its stock entry points.
 *
 * Beyond the real scripts on disk, core answers a handful of virtual paths —
 * `/login`, `/wp-login.php`, `/admin`, `/dashboard` — by redirecting them, in
 * `wp_redirect_admin_locations()`. The login half of that redirect targets
 * `wp_login_url()`, which this package has rewritten to the secret slug: a
 * single request to `/wp-login.php` therefore answers with the secret in a
 * `Location` header, defeating the whole point of hiding it.
 *
 * These paths are not `DefaultEndpoint` in the sense of
 * {@see RequestContextPort::defaultEndpoint()}, which classifies the executing
 * script; they are aliases resolved much later, once the request is already
 * known to be a 404. Hence a separate decision, kept here as a pure function of
 * its inputs so it can be exhaustively tested without WordPress.
 */
final class ClassifyStockAlias
{
    /**
     * Classifies a request against the two alias lists.
     *
     * Paths are compared through {@see RequestPath}, so trailing slashes, an
     * explicit `index.php` front controller, percent-encoding and a query
     * string all normalise away. That is marginally stricter than core, which
     * compares the raw `REQUEST_URI` and therefore ignores `/wp-login.php?x=1`;
     * such a request is a 404 either way, so the stricter reading only ever
     * removes a redirect that would have leaked the slug.
     *
     * Login is tested first. The lists do not overlap on a stock installation,
     * but should a host ever make them, refusing is the safe reading.
     *
     * @param  string  $requestUri  Raw value, typically `$_SERVER['REQUEST_URI']`.
     * @param  list<string>  $adminAliases  Paths core maps to the admin URL.
     * @param  list<string>  $loginAliases  Paths core maps to the login URL.
     * @return DefaultEndpoint|null The entry point aliased, or null when the request targets neither.
     */
    public function classify(string $requestUri, array $adminAliases, array $loginAliases): ?DefaultEndpoint
    {
        $path = RequestPath::fromRequestUri($requestUri)->value();

        if ($this->matchesAny($path, $loginAliases)) {
            return DefaultEndpoint::Login;
        }

        if ($this->matchesAny($path, $adminAliases)) {
            return DefaultEndpoint::Admin;
        }

        return null;
    }

    /**
     * Whether the normalised path equals any of the normalised candidates.
     *
     * @param  string  $path  Normalised request path.
     * @param  list<string>  $candidates  Alias paths, in whatever shape core produced them.
     */
    private function matchesAny(string $path, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if (RequestPath::fromRequestUri($candidate)->value() === $path) {
                return true;
            }
        }

        return false;
    }
}
