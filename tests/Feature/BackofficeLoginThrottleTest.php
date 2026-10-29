<?php

declare(strict_types=1);

use App\Actions\Auth\ThrottleBackofficeLoginAction;
use App\Enums\BackofficeLoginThrottleStep;
use App\Livewire\Auth\Login;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

it('blokkeert de volgende beheerlogin na herhaalde mislukte pogingen van hetzelfde IP', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $component = Livewire::test(Login::class)->set('email', $user->email);

    for ($i = 0; $i < ThrottleBackofficeLoginAction::PAIR_MAX_ATTEMPTS; $i++) {
        $component->set('password', 'fout-wachtwoord')->call('login')->assertHasErrors('email');
        expect(auth()->check())->toBeFalse();
    }

    $component->set('password', 'password')->call('login')->assertHasErrors('email');

    expect(auth()->check())->toBeFalse();

    $sample = __('auth.errors.throttle', ['seconds' => '99999']);
    $prefix = strstr($sample, '99999', true);
    $component->assertSee($prefix, false);
    expect(AuditLog::query()->where('action', 'auth.login_throttled')->count())->toBe(1);
});

it('blokkeert een e-mailadres ook vanaf een ander IP', function () {
    $action = app(ThrottleBackofficeLoginAction::class);
    $email = 'spread-login@example.com';

    for ($i = 0; $i < ThrottleBackofficeLoginAction::EMAIL_MAX_ATTEMPTS; $i++) {
        $action->handle($email, '203.0.113.'.$i, BackofficeLoginThrottleStep::Failure);
    }

    expect($action->handle($email, '198.51.100.20', BackofficeLoginThrottleStep::Check))->not->toBeNull();
});

it('blokkeert een IP na veel verschillende e-mailadressen', function () {
    $action = app(ThrottleBackofficeLoginAction::class);
    $ip = '198.51.100.40';

    for ($i = 0; $i < ThrottleBackofficeLoginAction::IP_MAX_ATTEMPTS; $i++) {
        $action->handle('ip-limit-'.$i.'@example.com', $ip, BackofficeLoginThrottleStep::Failure);
    }

    expect($action->handle('fresh-on-same-ip@example.com', $ip, BackofficeLoginThrottleStep::Check))->not->toBeNull()
        ->and($action->handle('fresh-on-same-ip@example.com', '198.51.100.41', BackofficeLoginThrottleStep::Check))->toBeNull();
});

it('wist de e-mailtellers na een geslaagde login', function () {
    $action = app(ThrottleBackofficeLoginAction::class);
    $email = 'cleared-login@example.com';
    $ip = '203.0.113.80';

    for ($i = 0; $i < ThrottleBackofficeLoginAction::PAIR_MAX_ATTEMPTS - 1; $i++) {
        $action->handle($email, $ip, BackofficeLoginThrottleStep::Failure);
    }

    $action->handle($email, $ip, BackofficeLoginThrottleStep::Success);

    expect($action->handle($email, $ip, BackofficeLoginThrottleStep::Check))->toBeNull();
});

it('logt geen throttle voor een onbekend e-mailadres', function () {
    $action = app(ThrottleBackofficeLoginAction::class);

    for ($i = 0; $i < ThrottleBackofficeLoginAction::PAIR_MAX_ATTEMPTS; $i++) {
        $action->handle('unknown-login@example.com', '203.0.113.90', BackofficeLoginThrottleStep::Failure);
    }

    expect(AuditLog::query()->where('action', 'auth.login_throttled')->exists())->toBeFalse();
});
