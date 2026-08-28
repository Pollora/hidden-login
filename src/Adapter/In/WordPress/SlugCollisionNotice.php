<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Adapter\In\WordPress;

use Pollora\HiddenLogin\Domain\Model\LoginSlug;
use Pollora\HiddenLogin\Port\Out\HookRegistrarPort;

/**
 * Warns administrators when published content is shadowed by the login slug.
 *
 * The router answers the slug before WordPress ever parses the request, so a
 * page sharing that slug becomes silently unreachable. The failure is invisible
 * from the editor's point of view — the page exists, it is published, it simply
 * never renders — which makes it worth an explicit notice.
 */
final class SlugCollisionNotice
{
    /**
     * @param  LoginSlug  $slug  The configured login slug.
     * @param  HookRegistrarPort  $hooks  Hook system of the host.
     */
    public function __construct(
        private readonly LoginSlug $slug,
        private readonly HookRegistrarPort $hooks,
    ) {}

    /**
     * Registers the admin notice.
     */
    public function register(): void
    {
        $this->hooks->addAction('admin_notices', [$this, 'render'], 10, 0);
    }

    /**
     * Renders the notice when a collision is detected.
     *
     * @internal Hooked on `admin_notices`; not part of the public API.
     */
    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $collision = get_page_by_path($this->slug->value(), OBJECT, $this->publicPostTypes());

        if ($collision === null) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            esc_html(sprintf(
                /* translators: 1: content title, 2: login slug */
                __('The content "%1$s" uses the slug "%2$s", which is reserved for the login screen: it can no longer be reached from the front end. Rename it, or change HIDDEN_LOGIN_SLUG.', 'hidden-login'),
                (string) $collision->post_title,
                $this->slug->value(),
            ))
        );
    }

    /**
     * Public post types whose permalinks could collide with the slug.
     *
     * @return list<string>
     */
    private function publicPostTypes(): array
    {
        return array_values(get_post_types(['public' => true], 'names'));
    }
}
