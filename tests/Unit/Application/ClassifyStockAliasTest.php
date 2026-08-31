<?php

declare(strict_types=1);

use Pollora\HiddenLogin\Application\Service\ClassifyStockAlias;
use Pollora\HiddenLogin\Domain\Model\DefaultEndpoint;

beforeEach(function (): void {
    $this->classifier = new ClassifyStockAlias;

    // The lists core builds on a root installation with pretty permalinks.
    $this->admins = ['/wp-admin', '/dashboard', '/admin', '/dashboard', '/admin'];
    $this->logins = ['/wp-login.php', '/login.php', '/login', '/login'];
});

it('recognises every login alias core would have redirected', function (string $uri): void {
    expect($this->classifier->classify($uri, $this->admins, $this->logins))
        ->toBe(DefaultEndpoint::Login);
})->with([
    '/wp-login.php',
    '/login.php',
    '/login',
]);

it('recognises every admin alias', function (string $uri): void {
    expect($this->classifier->classify($uri, $this->admins, $this->logins))
        ->toBe(DefaultEndpoint::Admin);
})->with([
    '/wp-admin',
    '/dashboard',
    '/admin',
]);

it('ignores a path that aliases neither', function (string $uri): void {
    expect($this->classifier->classify($uri, $this->admins, $this->logins))->toBeNull();
})->with([
    '/',
    '/contact',
    '/login-page',
    '/wp-login.php.bak',
    '/a/login',
]);

it('normalises trailing slashes on both sides', function (): void {
    expect($this->classifier->classify('/login/', $this->admins, $this->logins))
        ->toBe(DefaultEndpoint::Login)
        ->and($this->classifier->classify('/login', $this->admins, ['/login/']))
        ->toBe(DefaultEndpoint::Login);
});

it('classifies a login alias carrying a query string', function (): void {
    // Core compares the raw REQUEST_URI and so ignores this one; it is a 404
    // either way, and reading it as a login alias only ever withholds a
    // redirect that would have disclosed the slug.
    expect($this->classifier->classify('/wp-login.php?redirect_to=%2Fwp-admin%2F', $this->admins, $this->logins))
        ->toBe(DefaultEndpoint::Login);
});

it('sees through an explicit index.php front controller', function (): void {
    expect($this->classifier->classify('/index.php/login', $this->admins, $this->logins))
        ->toBe(DefaultEndpoint::Login);
});

it('handles a subdirectory installation', function (): void {
    // On /blog, core emits the prefix in both lists, so the comparison stays
    // an equality between two equally prefixed paths.
    expect($this->classifier->classify('/blog/wp-login.php', ['/blog/admin'], ['/blog/wp-login.php']))
        ->toBe(DefaultEndpoint::Login)
        ->and($this->classifier->classify('/wp-login.php', ['/blog/admin'], ['/blog/wp-login.php']))
        ->toBeNull();
});

it('prefers the login verdict when a host makes the lists overlap', function (): void {
    // Refusing is the safe reading of an ambiguous configuration.
    expect($this->classifier->classify('/login', ['/login'], ['/login']))
        ->toBe(DefaultEndpoint::Login);
});

it('returns null when both lists are empty', function (): void {
    expect($this->classifier->classify('/wp-login.php', [], []))->toBeNull();
});
