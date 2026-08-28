<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Domain\Exception;

use InvalidArgumentException;
use Pollora\HiddenLogin\Domain\Model\LoginSlug;

/**
 * Thrown when the configured login slug cannot be turned into a valid
 * {@see LoginSlug}.
 *
 * The composition root is expected to catch this exception and leave the whole
 * package dormant rather than letting it bubble up: a misconfigured slug must
 * never take a site down, and must never lock an administrator out.
 */
final class InvalidLoginSlugException extends InvalidArgumentException
{
    /**
     * The configured value contained only separators or whitespace.
     */
    public static function empty(): self
    {
        return new self('The login slug is empty once trimmed of slashes and whitespace.');
    }

    /**
     * The configured value is not a single, URL-safe path segment.
     *
     * @param  string  $slug  The offending value, as configured.
     */
    public static function malformed(string $slug): self
    {
        return new self(sprintf(
            'The login slug "%s" is not a valid path segment: use lowercase letters, digits, hyphens and underscores, starting and ending with a letter or a digit.',
            $slug
        ));
    }

    /**
     * The configured value collides with a path WordPress already owns.
     *
     * @param  string  $slug  The offending value, as configured.
     */
    public static function reserved(string $slug): self
    {
        return new self(sprintf(
            'The login slug "%s" is reserved by WordPress and cannot be used to expose the login screen.',
            $slug
        ));
    }

    /**
     * The configured value is short enough to be brute-forced or guessed.
     *
     * @param  string  $slug  The offending value, as configured.
     * @param  int  $minimum  The minimum number of characters required.
     */
    public static function tooShort(string $slug, int $minimum): self
    {
        return new self(sprintf(
            'The login slug "%s" is too short: %d characters are required to make the endpoint non-trivial to guess.',
            $slug,
            $minimum
        ));
    }
}
