<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Domain\Model;

/**
 * Whether the package is allowed to touch the request at all.
 *
 * This is the kill switch, and it is distinct from "is a slug configured".
 * A slug can legitimately be absent — nothing to serve, nothing to hide — while
 * this state answers a different question: should the package be part of this
 * installation in the first place.
 *
 * The default is {@see self::Enabled}. The package auto-registers itself through
 * Composer, so an installation that pulled it in has opted in by definition;
 * turning it off has to be a deliberate act.
 */
enum FeatureState
{
    /**
     * The package registers its hooks. Default.
     */
    case Enabled;

    /**
     * The package stays out of the request entirely.
     */
    case Disabled;

    /**
     * Values that switch the feature off, compared case-insensitively.
     *
     * Anything else — including unrecognised strings — enables the feature.
     * Erring towards enabled is deliberate: a typo in the configuration must not
     * silently drop a security control.
     *
     * @var list<string>
     */
    private const FALSY = ['false', '0', 'off', 'no', 'disabled'];

    /**
     * Reads the state from a raw configuration value.
     *
     * The value arrives in three shapes depending on how the host declares it.
     * Bedrock's `Config::define('HIDDEN_LOGIN_ENABLED', env('HIDDEN_LOGIN_ENABLED'))`
     * yields a real boolean, because `oscarotero/env` converts `"false"` on the
     * way in; a plain `getenv()` yields a string; an absent variable yields
     * `null`.
     *
     * @param  bool|string|null  $value  Raw value, or `null` when unset.
     */
    public static function fromConfiguredValue(bool|string|null $value): self
    {
        if ($value === null) {
            return self::Enabled;
        }

        if (is_bool($value)) {
            return $value ? self::Enabled : self::Disabled;
        }

        $normalised = strtolower(trim($value));

        if ($normalised === '') {
            return self::Enabled;
        }

        return in_array($normalised, self::FALSY, true) ? self::Disabled : self::Enabled;
    }

    /**
     * Whether the package may register itself.
     */
    public function isEnabled(): bool
    {
        return $this === self::Enabled;
    }
}
