<?php

declare(strict_types=1);

use Pollora\HiddenLogin\Domain\Model\FeatureState;

it('is enabled when nothing is configured', function (bool|string|null $value): void {
    // The package registers itself through Composer, so an installation that
    // pulled it in has opted in: switching it off has to be deliberate.
    expect(FeatureState::fromConfiguredValue($value)->isEnabled())->toBeTrue();
})->with([
    [null],
    [''],
    ['   '],
]);

it('is disabled by an explicit falsy value', function (bool|string $value): void {
    expect(FeatureState::fromConfiguredValue($value)->isEnabled())->toBeFalse();
})->with([
    [false],
    ['false'],
    ['FALSE'],
    [' False '],
    ['0'],
    ['off'],
    ['no'],
    ['disabled'],
]);

it('is enabled by an explicit truthy value', function (bool|string $value): void {
    expect(FeatureState::fromConfiguredValue($value)->isEnabled())->toBeTrue();
})->with([
    [true],
    ['true'],
    ['1'],
    ['on'],
    ['yes'],
]);

it('errs towards enabled on an unrecognised value', function (): void {
    // A typo in the configuration must not silently drop a security control.
    expect(FeatureState::fromConfiguredValue('flase')->isEnabled())->toBeTrue();
});
