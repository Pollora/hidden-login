<?php

declare(strict_types=1);

use Pollora\HiddenLogin\Domain\Model\LoginSlug;
use Pollora\HiddenLogin\Domain\Model\RequestPath;

it('strips the query string, the slashes and the percent encoding', function (string $uri, string $expected): void {
    expect(RequestPath::fromRequestUri($uri)->value())->toBe($expected);
})->with([
    ['/connexion', 'connexion'],
    ['/connexion/', 'connexion'],
    ['connexion', 'connexion'],
    ['/connexion?action=lostpassword', 'connexion'],
    ['/connexion/?action=rp&key=abc', 'connexion'],
    ['/mon%20espace', 'mon espace'],
    ['/', ''],
    ['', ''],
]);

it('ignores an explicit front controller', function (string $uri): void {
    expect(RequestPath::fromRequestUri($uri)->value())->toBe('connexion');
})->with([
    '/index.php/connexion',
    'index.php/connexion',
    '/index.php/connexion/?action=logout',
]);

it('keeps a path that merely starts like the front controller', function (): void {
    expect(RequestPath::fromRequestUri('/index.php-backup/connexion')->value())
        ->toBe('index.php-backup/connexion');
});

it('strips the home path of a subdirectory installation', function (string $uri, string $home, string $expected): void {
    expect(RequestPath::fromRequestUri($uri)->relativeTo($home)?->value())->toBe($expected);
})->with([
    ['/blog/connexion', 'blog', 'connexion'],
    ['/blog/connexion', '/blog/', 'connexion'],
    ['/blog', 'blog', ''],
    ['/connexion', '', 'connexion'],
]);

it('has no relative form outside the installation', function (string $uri, string $home): void {
    // Returning the absolute path here would let /connexion match on an
    // installation served from /blog, which is a silent false positive.
    expect(RequestPath::fromRequestUri($uri)->relativeTo($home))->toBeNull();
})->with([
    // Shares a prefix with the home path but is not below it.
    ['/blogue/connexion', 'blog'],
    ['/connexion', 'blog'],
    ['/', 'blog'],
]);

it('matches the configured slug', function (): void {
    $slug = LoginSlug::fromString('connexion');

    expect(RequestPath::fromRequestUri('/connexion/?action=lostpassword')->matches($slug))->toBeTrue()
        ->and(RequestPath::fromRequestUri('/connexions')->matches($slug))->toBeFalse()
        ->and(RequestPath::fromRequestUri('/connexion/extra')->matches($slug))->toBeFalse()
        ->and(RequestPath::fromRequestUri('/Connexion')->matches($slug))->toBeFalse();
});
