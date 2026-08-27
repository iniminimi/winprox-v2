<?php

use App\Enums\EmailUnsubscribeSource;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\VerifyEmailNotice;
use App\Mail\VerifyUserEmailMail;
use App\Models\AuditLog;
use App\Models\EmailUnsubscribe;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\Support\RegisterFormData;

afterEach(fn () => Tenancy::forget());

function unverifiedTenantAdmin(): User
{
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(20)]);

    return User::factory()->admin()->unverified()->create(['tenant_id' => $tenant->id]);
}

it('stuurt bij registratie een verificatiemail en laat het account onbevestigd', function () {
    Mail::fake();

    Livewire::test(Register::class)
        ->set(RegisterFormData::valid())
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('verification.notice'));

    $user = User::query()->where('email', 'nieuw@winprox.test')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and(session('register_success'))->toBeTrue();

    Mail::assertSent(
        VerifyUserEmailMail::class,
        fn (VerifyUserEmailMail $mail) => $mail->hasTo('nieuw@winprox.test'),
    );
});

it('toont welkomstvideo en account-aangemaakt op het bevestigingsscherm na registratie', function () {
    $user = unverifiedTenantAdmin();

    session(['register_success' => true]);

    Livewire::actingAs($user)
        ->test(VerifyEmailNotice::class)
        ->assertSet('showWelcome', true)
        ->assertSee(__('auth.verify.welcome_title'))
        ->assertSee(__('auth.verify.title'))
        ->assertSee('video/assistant_task_160.mp4', false);
});

it('weigert registratie naar een eerder gebouncet e-mailadres', function () {
    Mail::fake();

    EmailUnsubscribe::query()->create([
        'email' => 'bounce@example.com',
        'source' => EmailUnsubscribeSource::Undeliverable,
        'unsubscribed_at' => now(),
    ]);

    Livewire::test(Register::class)
        ->set(RegisterFormData::valid())
        ->set('email', 'bounce@example.com')
        ->call('register')
        ->assertHasErrors('email');

    expect(User::query()->where('email', 'bounce@example.com')->exists())->toBeFalse()
        ->and(Tenant::query()->where('email', 'bounce@example.com')->exists())->toBeFalse();

    Mail::assertNotSent(VerifyUserEmailMail::class);
});

it('stuurt een account-bevestigingsmail ook naar een uitgeschreven adres', function () {
    Mail::fake();

    $user = unverifiedTenantAdmin();
    $user->update(['email' => 'unsub@example.com']);

    EmailUnsubscribe::query()->create([
        'email' => 'unsub@example.com',
        'source' => EmailUnsubscribeSource::Voluntary,
        'unsubscribed_at' => now(),
    ]);

    Livewire::actingAs($user)->test(VerifyEmailNotice::class)
        ->call('resend')
        ->assertHasNoErrors()
        ->assertSet('status', __('auth.verify.resent'));

    Mail::assertSent(VerifyUserEmailMail::class);
});

it('houdt beheerschermen dicht tot het e-mailadres bevestigd is', function () {
    $user = unverifiedTenantAdmin();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk();
});

it('laat publieke pagina’s open voor een onbevestigde gebruiker', function () {
    $user = unverifiedTenantAdmin();

    $this->actingAs($user)
        ->get(route('pricing', ['locale' => 'nl']))
        ->assertOk();
});

it('bevestigt het e-mailadres via de ondertekende link en logt dat', function () {
    $user = unverifiedTenantAdmin();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(route('dashboard'));

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue()
        ->and(AuditLog::query()
            ->where('action', 'auth.email_verified')
            ->where('tenant_id', $user->tenant_id)
            ->exists())->toBeTrue();
});

it('weigert een verificatielink met een verkeerde hash', function () {
    $user = unverifiedTenantAdmin();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1('ander@adres.test'),
    ]);

    $this->actingAs($user)->get($url)->assertForbidden();

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('beperkt het opnieuw versturen van de verificatiemail', function () {
    Mail::fake();

    $user = unverifiedTenantAdmin();

    $component = Livewire::actingAs($user)->test(VerifyEmailNotice::class);

    for ($i = 0; $i < 3; $i++) {
        $component->call('resend')->assertSet('status', __('auth.verify.resent'));
    }

    $component->call('resend');

    expect($component->get('status'))->not->toBe(__('auth.verify.resent'));

    Mail::assertSent(VerifyUserEmailMail::class, 3);
});
