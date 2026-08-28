<?php

declare(strict_types=1);

namespace Pollora\HiddenLogin\Domain\Model;

/**
 * The stock WordPress entry points that this package hides.
 *
 * Both are reachable as real files on disk, so they cannot be hidden by
 * rewriting rules alone: they have to be recognised once WordPress is booted
 * and answered with a 404 from PHP.
 */
enum DefaultEndpoint
{
    /**
     * `wp-login.php`, whatever the requested `action` is.
     *
     * Note that `WP_ADMIN` is *not* defined while this script runs, which is
     * what makes it possible to answer with the theme's own 404 template.
     */
    case Login;

    /**
     * Any script under `wp-admin/`, except the ones the front end depends on.
     *
     * `admin-ajax.php` and `admin-post.php` are legitimate endpoints for logged
     * out visitors and are therefore never classified as this endpoint.
     */
    case Admin;
}
