<?php

declare(strict_types=1);

use Pollora\HiddenLogin\Application\Service\MatchHiddenLoginRequest;
use Pollora\HiddenLogin\Domain\Model\LoginSlug;

beforeEach(function (): void {
    $this->matcher = new MatchHiddenLoginRequest;
    $this->slug = LoginSlug::fromString('acces-prive');
});

it('matches the slug whatever the trailing slash and the query string', function (string $uri): void {
    expect($this->matcher->matches($uri, '', $this->slug))->toBeTrue();
})->with([
    '/acces-prive',
    '/acces-prive/',
    '/acces-prive?action=lostpassword',
    '/acces-prive/?action=rp&key=abc&login=admin',
    '/index.php/acces-prive',
]);

it('does not match anything else', function (string $uri): void {
    expect($this->matcher->matches($uri, '', $this->slug))->toBeFalse();
})->with([
    '/',
    '/acces-prive/wp-login.php',
    '/acces-privee',
    '/wp/wp-login.php',
    '/wp/wp-admin/',
    '/blog/acces-prive',
]);

it('matches below the home path on a subdirectory installation', function (): void {
    expect($this->matcher->matches('/blog/acces-prive', 'blog', $this->slug))->toBeTrue()
        ->and($this->matcher->matches('/acces-prive', 'blog', $this->slug))->toBeFalse();
});
