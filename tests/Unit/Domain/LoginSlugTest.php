<?php

declare(strict_types=1);

use Pollora\HiddenLogin\Domain\Exception\InvalidLoginSlugException;
use Pollora\HiddenLogin\Domain\Model\LoginSlug;

it('normalises surrounding slashes and whitespace', function (string $raw): void {
    expect(LoginSlug::fromString($raw)->value())->toBe('connexion');
})->with([
    'connexion',
    '/connexion',
    'connexion/',
    '/connexion/',
    '  /connexion/  ',
]);

it('exposes a root relative path without a trailing slash', function (): void {
    // The absence of a trailing slash is what keeps the wp-resetpass cookie
    // path aligned with the URL the reset form posts to.
    expect(LoginSlug::fromString('connexion')->toPath())->toBe('/connexion');
});

it('is stringable', function (): void {
    expect((string) LoginSlug::fromString('connexion'))->toBe('connexion');
});

it('rejects an empty value', function (): void {
    LoginSlug::fromString('   /   ');
})->throws(InvalidLoginSlugException::class);

it('rejects values that are not a single url safe segment', function (string $raw): void {
    LoginSlug::fromString($raw);
})->with([
    'mon espace',
    'Connexion',
    'connexion.php',
    'espace/connexion',
    '-connexion',
    'connexion-',
    'connexion?a=1',
    'connexión',
])->throws(InvalidLoginSlugException::class);

it('rejects slugs reserved by wordpress', function (string $raw): void {
    LoginSlug::fromString($raw);
})->with([
    'wp-admin',
    'wp-login.php',
    'wp-content',
    'wp-includes',
    'wp-json',
    'xmlrpc.php',
    'app',
])->throws(InvalidLoginSlugException::class);

it('rejects slugs short enough to be guessed', function (): void {
    LoginSlug::fromString('abcd');
})->throws(InvalidLoginSlugException::class);

it('accepts a slug exactly at the minimum length', function (): void {
    expect(LoginSlug::fromString('abcde')->value())->toBe('abcde');
});
