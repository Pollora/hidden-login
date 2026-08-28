<?php

declare(strict_types=1);

use Pollora\HiddenLogin\Application\Service\ResolveLoginSlug;
use Pollora\HiddenLogin\Domain\Exception\InvalidLoginSlugException;
use Pollora\HiddenLogin\Tests\Support\InMemorySlugProvider;

it('returns null when nothing is configured', function (?string $raw): void {
    expect((new ResolveLoginSlug(new InMemorySlugProvider($raw)))->resolve())->toBeNull();
})->with([
    [null],
    [''],
    ['   '],
]);

it('returns the validated slug when one is configured', function (): void {
    $slug = (new ResolveLoginSlug(new InMemorySlugProvider(' /acces-prive/ ')))->resolve();

    expect($slug)->not->toBeNull()
        ->and($slug?->value())->toBe('acces-prive');
});

it('surfaces a configured but unusable value', function (): void {
    // Distinct from "not configured": an operator meant to enable the feature
    // and got it wrong, which the composition root turns into an admin notice
    // instead of silently pretending the protection is on.
    (new ResolveLoginSlug(new InMemorySlugProvider('wp-admin')))->resolve();
})->throws(InvalidLoginSlugException::class);
