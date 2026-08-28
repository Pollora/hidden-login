<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Tests\Support;

use Pollora\HiddenLogin\Port\Out\SlugProviderPort;

/**
 * Test double returning a slug held in memory.
 */
final class InMemorySlugProvider implements SlugProviderPort
{
    /**
     * @param  string|null  $slug  The value the provider hands back.
     */
    public function __construct(private readonly ?string $slug) {}

    /**
     * {@inheritDoc}
     */
    public function slug(): ?string
    {
        return $this->slug;
    }
}
