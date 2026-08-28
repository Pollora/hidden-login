<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Domain\Model;

use Stringable;

/**
 * The normalised path of the incoming HTTP request.
 *
 * Normalisation removes everything that is irrelevant to routing — query
 * string, fragment, percent-encoding, the optional `index.php` front
 * controller, surrounding slashes — so that comparing a request to a
 * {@see LoginSlug} is a plain string comparison and not a pile of special
 * cases spread across the adapters.
 */
final class RequestPath implements Stringable
{
    /**
     * @param  string  $value  The normalised path, without leading or trailing slash.
     */
    private function __construct(private readonly string $value) {}

    /**
     * Builds a path from a raw `REQUEST_URI`.
     *
     * @param  string  $requestUri  Raw value, typically `$_SERVER['REQUEST_URI']`,
     *                              possibly containing a query string.
     */
    public static function fromRequestUri(string $requestUri): self
    {
        $path = parse_url($requestUri, PHP_URL_PATH);

        if (! is_string($path)) {
            $path = '';
        }

        // A request may be served through the front controller explicitly, as in
        // /index.php/my-slug. WordPress strips it when parsing the request, so
        // the comparison has to as well.
        $path = (string) preg_replace('#^/?index\.php(?=/|$)#i', '', $path);

        return new self(trim(rawurldecode($path), '/'));
    }

    /**
     * Returns the same path expressed relatively to the WordPress home path.
     *
     * On a subdirectory installation, `WP_HOME` carries a path prefix that is
     * present in every `REQUEST_URI` but absent from the slug. Stripping it here
     * keeps the comparison meaningful without leaking the notion of "home path"
     * into the matching logic.
     *
     * A path that does not sit below the home path has no relative form, and
     * `null` is returned. Falling back to the absolute path instead would make
     * `/secret-slug` match on an installation served from `/blog`, which is a
     * silent false positive rather than a lenient behaviour.
     *
     * @param  string  $homePath  Path component of the home URL, with or without slashes.
     * @return self|null `null` when the path is outside the installation.
     */
    public function relativeTo(string $homePath): ?self
    {
        $prefix = trim($homePath, '/');

        if ($prefix === '') {
            return $this;
        }

        if ($this->value === $prefix) {
            return new self('');
        }

        if (! str_starts_with($this->value, $prefix.'/')) {
            return null;
        }

        return new self(substr($this->value, strlen($prefix) + 1));
    }

    /**
     * Whether this path is exactly the given login slug.
     *
     * The comparison uses {@see hash_equals()} so that the time it takes to
     * reject a candidate does not depend on how many leading characters it got
     * right. On its own this would be a marginal gain, but the whole point of
     * the package is that the slug stays secret, so the cheap defence is worth
     * taking.
     *
     * @param  LoginSlug  $slug  The configured login slug.
     */
    public function matches(LoginSlug $slug): bool
    {
        return hash_equals($slug->value(), $this->value);
    }

    /**
     * The normalised path, without leading or trailing slash.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
