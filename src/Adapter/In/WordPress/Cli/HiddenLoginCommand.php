<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Adapter\In\WordPress\Cli;

use Pollora\HiddenLogin\Domain\Exception\InvalidLoginSlugException;
use Pollora\HiddenLogin\Domain\Model\LoginSlug;
use Pollora\HiddenLogin\Port\Out\SlugProviderPort;
use WP_CLI;

/**
 * Reports the effective login URL from a terminal.
 *
 * This is the recovery path. WP-CLI is explicitly excluded from routing, so the
 * command keeps answering even when the slug is wrong, when the constant was
 * lost in a deployment, or when someone has locked themselves out — situations
 * where every HTTP route into the site returns a 404 by design.
 */
final class HiddenLoginCommand
{
    /**
     * @param  SlugProviderPort  $provider  Source of the raw configuration value.
     */
    public function __construct(private readonly SlugProviderPort $provider) {}

    /**
     * Registers the `wp hidden-login` command when running under WP-CLI.
     *
     * @param  SlugProviderPort  $provider  Source of the raw configuration value.
     */
    public static function register(SlugProviderPort $provider): void
    {
        if (! defined('WP_CLI') || ! WP_CLI) {
            return;
        }

        WP_CLI::add_command('hidden-login', new self($provider));
    }

    /**
     * Prints the URL the login screen is served from.
     *
     * ## EXAMPLES
     *
     *     wp hidden-login url
     *
     * @subcommand url
     */
    public function url(): void
    {
        try {
            $slug = $this->slug();
        } catch (InvalidLoginSlugException $exception) {
            WP_CLI::error($exception->getMessage());

            return;
        }

        if ($slug === null) {
            WP_CLI::error('No login slug is configured: WordPress serves wp-login.php as usual.');

            return;
        }

        WP_CLI::line(home_url($slug->toPath()));
    }

    /**
     * Prints the current state of the protection.
     *
     * ## EXAMPLES
     *
     *     wp hidden-login status
     *
     * @subcommand status
     */
    public function status(): void
    {
        try {
            $slug = $this->slug();
        } catch (InvalidLoginSlugException $exception) {
            WP_CLI::line('hidden-login: inactive (the configured slug was rejected).');
            WP_CLI::error($exception->getMessage());

            return;
        }

        if ($slug === null) {
            WP_CLI::line('hidden-login: inactive (no slug configured).');
            WP_CLI::line('wp-login.php and wp-admin/ behave as WordPress intends.');

            return;
        }

        WP_CLI::line('hidden-login: active.');
        WP_CLI::line('Login URL:   '.home_url($slug->toPath()));
        WP_CLI::line('wp-login.php: 404 for everyone.');
        WP_CLI::line('wp-admin/:    404 for anonymous visitors, untouched once authenticated.');
    }

    /**
     * Resolves the configured slug.
     *
     * @return LoginSlug|null `null` when the feature is not configured at all.
     *
     * @throws InvalidLoginSlugException When a value is configured but unusable.
     */
    private function slug(): ?LoginSlug
    {
        $raw = $this->provider->slug();

        if ($raw === null || trim($raw) === '') {
            return null;
        }

        return LoginSlug::fromString($raw);
    }
}
