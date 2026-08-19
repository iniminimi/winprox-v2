<?php

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Mail\NewTenantRegisteredMail;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\Support\RegisterFormData;

afterEach(fn () => Tenancy::forget());

it('registreert een nieuwe tenant met beheerder en logt in', function () {
    Mail::fake();

    Livewire::test(Register::class)
        ->set(RegisterFormData::valid())
        ->call('register')
        ->assertHasNoErrors()
        ->assertDispatched('register-finished', redirectTo: route('verification.notice'));

    $tenant = Tenant::where('name', 'Nieuwe Facility')->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->email)->toBe('nieuw@winprox.test')
        ->and($tenant->street)->toBe('Bosrandstraat')
        ->and($tenant->country_code)->toBe('BE');

    $user = User::where('email', 'nieuw@winprox.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->tenant_id)->toBe($tenant->id)
        ->and($user->is_superuser)->toBeFalse();

    expect(auth()->check())->toBeTrue()
        ->and(auth()->id())->toBe($user->id);

    Mail::assertSent(NewTenantRegisteredMail::class, function (NewTenantRegisteredMail $mail) use ($tenant, $user) {
        return $mail->hasTo(config('winprox.new_tenant_notification_email'))
            && $mail->tenant->is($tenant)
            && $mail->admin->is($user);
    });
});

it('valideert de registratievelden', function () {
    Livewire::test(Register::class)
        ->set('organization', '')
        ->set('phone', 'abc')
        ->set('name', '')
        ->set('email', 'geen-geldig-adres')
        ->set('password', 'kort')
        ->set('password_confirmation', 'anders')
        ->set('accept_terms', false)
        ->call('register')
        ->assertHasErrors(['organization', 'phone', 'name', 'email', 'password', 'accept_terms']);

    expect(auth()->check())->toBeFalse();
});

it('toont de wachtwoord-vergeten pagina', function () {
    $this->get(route('password.request'))->assertOk();
});

it('verstuurt een herstellink via de password broker', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::test(ForgotPassword::class)
        ->set('email', $user->email)
        ->call('sendResetLink')
        ->assertHasNoErrors();

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('reset het wachtwoord met een geldig token', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'nieuwwachtwoord1')
        ->set('password_confirmation', 'nieuwwachtwoord1')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('nieuwwachtwoord1', $user->fresh()->password))->toBeTrue();
});
