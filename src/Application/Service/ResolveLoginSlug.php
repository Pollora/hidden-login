<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Application\Service;

use Pollora\HiddenLogin\Domain\Exception\InvalidLoginSlugException;
use Pollora\HiddenLogin\Domain\Model\LoginSlug;
use Pollora\HiddenLogin\Port\Out\SlugProviderPort;

/**
 * Turns the raw configuration value into a validated {@see LoginSlug}.
 *
 * This is the only place where "not configured" and "misconfigured" are told
 * apart. The distinction matters: the first is a normal state that must leave
 * WordPress strictly untouched, the second is an operator error that must be
 * surfaced rather than silently approximated.
 */
final class ResolveLoginSlug
{
    /**
     * @param  SlugProviderPort  $provider  Source of the raw configuration value.
     */
    public function __construct(private readonly SlugProviderPort $provider) {}

    /**
     * Resolves the configured slug.
     *
     * @return LoginSlug|null `null` when the feature is not configured at all.
     *
     * @throws InvalidLoginSlugException When a value is configured but unusable.
     */
    public function resolve(): ?LoginSlug
    {
        $raw = $this->provider->slug();

        if ($raw === null || trim($raw) === '') {
            return null;
        }

        return LoginSlug::fromString($raw);
    }
}
