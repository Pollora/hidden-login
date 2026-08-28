<?php

declare(strict_types=1);

use Pollora\HiddenLogin\Application\Service\GuardDefaultEndpoints;
use Pollora\HiddenLogin\Domain\Model\DefaultEndpoint;

beforeEach(function (): void {
    $this->guard = new GuardDefaultEndpoints;
});

it('blocks wp-login.php for anonymous visitors', function (): void {
    expect($this->guard->shouldBlock(DefaultEndpoint::Login, false, 'login'))->toBeTrue();
});

it('blocks wp-login.php for authenticated users too', function (): void {
    // Logging out, resetting a password or confirming a privacy request are all
    // reachable through the secret slug, so there is no reason to keep a second
    // door open for people who already hold a session.
    expect($this->guard->shouldBlock(DefaultEndpoint::Login, true, 'logout'))->toBeTrue();
});

it('blocks every wp-login.php action by default', function (string $action): void {
    expect($this->guard->shouldBlock(DefaultEndpoint::Login, false, $action))->toBeTrue();
})->with([
    'login',
    'logout',
    'lostpassword',
    'retrievepassword',
    'resetpass',
    'rp',
    'register',
    'postpass',
    'confirmaction',
]);

it('lets an explicitly allowed action through', function (): void {
    expect($this->guard->shouldBlock(DefaultEndpoint::Login, false, 'postpass', ['postpass']))->toBeFalse()
        ->and($this->guard->shouldBlock(DefaultEndpoint::Login, false, 'login', ['postpass']))->toBeTrue();
});

it('blocks wp-admin for anonymous visitors', function (): void {
    expect($this->guard->shouldBlock(DefaultEndpoint::Admin, false, 'login'))->toBeTrue();
});

it('leaves wp-admin alone once authenticated', function (): void {
    expect($this->guard->shouldBlock(DefaultEndpoint::Admin, true, 'login'))->toBeFalse();
});

it('does not apply the login allow list to wp-admin', function (): void {
    expect($this->guard->shouldBlock(DefaultEndpoint::Admin, false, 'postpass', ['postpass']))->toBeTrue();
});
