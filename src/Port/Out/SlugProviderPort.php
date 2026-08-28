<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Port\Out;

/**
 * Supplies the raw, unvalidated login slug from wherever the host application
 * chooses to store it.
 *
 * The default implementation reads a constant or an environment variable, which
 * keeps the secret out of the database and therefore out of production dumps
 * restored on staging or local environments. Hosts that need a settings screen
 * can implement this port against an option instead and inject it into
 * `HiddenLogin::boot()`.
 */
interface SlugProviderPort
{
    /**
     * The configured slug, or `null` when the feature is not configured.
     *
     * Returning `null` must leave the package completely dormant: an unset slug
     * is a legitimate state (a freshly provisioned environment, a local install)
     * and never an error.
     */
    public function slug(): ?string;
}
