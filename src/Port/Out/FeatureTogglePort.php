<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Port\Out;

use Pollora\HiddenLogin\Domain\Model\FeatureState;

/**
 * Tells the package whether it is allowed to register itself.
 *
 * Separate from the slug provider on purpose: "no slug configured" and "this
 * installation does not want the package" are different statements, and only
 * the second one should survive a slug being present in a shared `.env`.
 */
interface FeatureTogglePort
{
    /**
     * The configured state, defaulting to enabled when nothing is set.
     */
    public function state(): FeatureState;
}
