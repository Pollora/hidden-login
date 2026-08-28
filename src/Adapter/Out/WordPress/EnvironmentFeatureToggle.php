<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Adapter\Out\WordPress;

use Pollora\HiddenLogin\Domain\Model\FeatureState;
use Pollora\HiddenLogin\Port\Out\FeatureTogglePort;

/**
 * Reads the kill switch from a PHP constant, falling back to the environment.
 *
 * Same lookup order and rationale as {@see EnvironmentSlugProvider}: the
 * constant first, because Bedrock-style installations define it from `.env`
 * before WordPress boots, then the raw environment for hosts that export it
 * from the web server or the container.
 */
final class EnvironmentFeatureToggle implements FeatureTogglePort
{
    /**
     * Name of the constant and of the environment variable holding the switch.
     */
    public const KEY = 'HIDDEN_LOGIN_ENABLED';

    /**
     * @param  string  $key  Overridable for hosts that already own the default name.
     */
    public function __construct(private readonly string $key = self::KEY) {}

    /**
     * {@inheritDoc}
     */
    public function state(): FeatureState
    {
        return FeatureState::fromConfiguredValue($this->configuredValue());
    }

    /**
     * The raw value, or `null` when nothing is configured.
     *
     * A constant defined as `null` — what `Config::define(..., env(...))`
     * produces for an absent variable — counts as unset, and therefore as
     * enabled.
     */
    private function configuredValue(): bool|string|null
    {
        if (defined($this->key)) {
            $value = constant($this->key);

            if (is_bool($value) || is_string($value)) {
                return $value;
            }

            return null;
        }

        $value = $_ENV[$this->key] ?? $_SERVER[$this->key] ?? getenv($this->key);

        return is_string($value) ? $value : null;
    }
}
