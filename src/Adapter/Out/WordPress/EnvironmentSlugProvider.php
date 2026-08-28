<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Adapter\Out\WordPress;

use Pollora\HiddenLogin\Port\Out\SlugProviderPort;

/**
 * Reads the login slug from a PHP constant, falling back to the environment.
 *
 * The constant is looked up first because Bedrock-style installations define it
 * from `.env` in `config/application.php`, which makes it available to every
 * SAPI — web, WP-CLI and cron alike — before WordPress even boots.
 *
 * Nothing is read from the database on purpose. A slug stored as an option
 * would travel with production dumps restored on staging and local machines,
 * where it would either leak the production secret or lock developers out of an
 * environment they cannot reach a terminal on.
 */
final class EnvironmentSlugProvider implements SlugProviderPort
{
    /**
     * Name of the constant and of the environment variable holding the slug.
     */
    public const KEY = 'HIDDEN_LOGIN_SLUG';

    /**
     * @param  string  $key  Overridable for hosts that already own the default name.
     */
    public function __construct(private readonly string $key = self::KEY) {}

    /**
     * {@inheritDoc}
     */
    public function slug(): ?string
    {
        $fromConstant = $this->fromConstant();

        if ($fromConstant !== null) {
            return $fromConstant;
        }

        return $this->fromEnvironment();
    }

    /**
     * Reads the slug from a PHP constant.
     *
     * A constant defined with a `null` or non-string value — which is what
     * `Config::define('HIDDEN_LOGIN_SLUG', env('HIDDEN_LOGIN_SLUG') ?: null)`
     * produces when the variable is absent — is treated as "not configured".
     */
    private function fromConstant(): ?string
    {
        if (! defined($this->key)) {
            return null;
        }

        $value = constant($this->key);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * Reads the slug from the process environment.
     *
     * Covers installations that do not go through Bedrock's configuration layer
     * and simply export the variable from the web server or the container.
     */
    private function fromEnvironment(): ?string
    {
        $value = $_ENV[$this->key] ?? $_SERVER[$this->key] ?? getenv($this->key);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
