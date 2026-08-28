<?php

declare(strict_types=1);

use Pollora\HiddenLogin\Application\Service\RewriteLoginUrl;
use Pollora\HiddenLogin\Domain\Model\LoginSlug;

beforeEach(function (): void {
    $this->rewriter = new RewriteLoginUrl;
    $this->slug = LoginSlug::fromString('acces-prive');
    $this->base = 'https://example.com';
});

it('leaves urls unrelated to the login untouched', function (string $url): void {
    expect($this->rewriter->applies($url))->toBeFalse()
        ->and($this->rewriter->rewrite($url, $this->base, $this->slug))->toBe($url);
})->with([
    'https://example.com/wp/wp-admin/',
    'https://example.com/boutique/',
    'https://example.com/wp-json/wp/v2/posts',
    '/mon-compte/',
]);

it('moves the bedrock login url to the public root', function (): void {
    expect($this->rewriter->rewrite('https://example.com/wp/wp-login.php', $this->base, $this->slug))
        ->toBe('https://example.com/acces-prive');
});

it('preserves the query string carrying the action and the reset key', function (): void {
    $url = 'https://example.com/wp/wp-login.php?action=rp&key=Abc123&login=admin';

    expect($this->rewriter->rewrite($url, $this->base, $this->slug))
        ->toBe('https://example.com/acces-prive?action=rp&key=Abc123&login=admin');
});

it('preserves the fragment', function (): void {
    expect($this->rewriter->rewrite('https://example.com/wp/wp-login.php?action=register#form', $this->base, $this->slug))
        ->toBe('https://example.com/acces-prive?action=register#form');
});

it('absolutises the relative redirects wp-login.php emits', function (): void {
    // wp_safe_redirect( 'wp-login.php?checkemail=confirm' ) after a lost
    // password request: left relative, the browser would resolve it against the
    // secret slug and land on /acces-prive/wp-login.php.
    expect($this->rewriter->rewrite('wp-login.php?checkemail=confirm', $this->base, $this->slug))
        ->toBe('https://example.com/acces-prive?checkemail=confirm');
});

it('honours the scheme carried by the base url', function (): void {
    expect($this->rewriter->rewrite('http://example.com/wp/wp-login.php', 'https://example.com', $this->slug))
        ->toBe('https://example.com/acces-prive');
});

it('tolerates a base url with a trailing slash', function (): void {
    expect($this->rewriter->rewrite('https://example.com/wp/wp-login.php', 'https://example.com/', $this->slug))
        ->toBe('https://example.com/acces-prive');
});

it('keeps the home path of a subdirectory installation', function (): void {
    expect($this->rewriter->rewrite('https://example.com/blog/wp/wp-login.php', 'https://example.com/blog', $this->slug))
        ->toBe('https://example.com/blog/acces-prive');
});
