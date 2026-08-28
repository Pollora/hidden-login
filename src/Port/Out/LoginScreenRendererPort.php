<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Port\Out;

use Pollora\HiddenLogin\Domain\Model\LoginSlug;

/**
 * Renders the native login screen in response to a request on the secret slug.
 *
 * Rendering is split in two steps because they must happen at different points
 * of the WordPress boot sequence: the request environment has to be rewritten
 * before plugins inspect it, whereas the screen itself can only be produced
 * once `$wp`, `$wp_query` and `$wp_rewrite` exist.
 */
interface LoginScreenRendererPort
{
    /**
     * Makes the runtime believe the request targets the login screen.
     *
     * Called as early as possible so that anything hooking later — security
     * plugins, two-factor providers, single sign-on — sees a coherent context.
     *
     * @param  LoginSlug  $slug  The slug the request matched.
     */
    public function prepare(LoginSlug $slug): void;

    /**
     * Renders the login screen and terminates the request.
     */
    public function render(): never;
}
