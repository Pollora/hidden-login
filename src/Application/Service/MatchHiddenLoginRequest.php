<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Application\Service;

use Pollora\HiddenLogin\Domain\Model\LoginSlug;
use Pollora\HiddenLogin\Domain\Model\RequestPath;

/**
 * Decides whether the incoming request targets the secret login URL.
 *
 * Kept as a service rather than inlined in the router so that the normalisation
 * rules (trailing slash, percent-encoding, `index.php` front controller,
 * subdirectory installs) are covered by unit tests without booting WordPress.
 */
final class MatchHiddenLoginRequest
{
    /**
     * @param  string  $requestUri  Raw request URI, query string included.
     * @param  string  $homePath  Path component of the site's home URL.
     * @param  LoginSlug  $slug  The configured login slug.
     */
    public function matches(string $requestUri, string $homePath, LoginSlug $slug): bool
    {
        $path = RequestPath::fromRequestUri($requestUri)->relativeTo($homePath);

        return $path !== null && $path->matches($slug);
    }
}
