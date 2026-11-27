<?php

use App\Actions\Auth\ConfirmPhoneLoginAction;
use App\Actions\Auth\VerifyUserPasskeyAssertionAction;
use App\Actions\Auth\ConsumeBackofficeLoginChallengeAction;
use App\Actions\Auth\RecordBackofficeLoginAction;
use App\Actions\Auth\ReleaseUserPhoneAction;
use App\Actions\Auth\StartBackofficeLoginChallengeAction;
use App\Actions\Auth\SubmitPhonePasswordAction;
use App\Http\Middleware\EnsureAbsoluteSessionLifetime;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\PhoneLogin;
use App\Livewire\Auth\Register;
use App\Livewire\Dashboard;
use App\Livewire\Pages\Settings;
use App\Models\BackofficeLoginChallenge;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPasskeyCredential;
use App\Support\Auth\BackofficePassword;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function replaceHttpFakes(mixed $callback): void
{
    $factory = app(Illuminate\Http\Client\Factory::class);
    (new ReflectionProperty($factory, 'stubCallbacks'))
        ->setValue($factory, new Illuminate\Support\Collection);

    Http::fake($callback);
}

it('weigert een kort wachtwoord en een gelekt wachtwoord, en laat door als de lekservice uit ligt', function () {
    $short = validator(
        ['password' => 'kortwoord', 'password_confirmation' => 'kortwoord'],
        ['password' => [...BackofficePassword::constraints(), 'confirmed']],
    );
    expect($short->fails())->toBeTrue();

    $hash = strtoupper(sha1('wachtwoord123'));
    replaceHttpFakes([
        'api.pwnedpasswords.com/*' => Http::response(substr($hash, 5).":5\n", 200),
    ]);
    $leaked = validator(
        ['password' => 'wachtwoord123', 'password_confirmation' => 'wachtwoord123'],
        ['password' => [...BackofficePassword::constraints(), 'confirmed']],
    );
    expect($leaked->fails())->toBeTrue();

    replaceHttpFakes(function () {
        throw new ConnectionException('down');
    });
    $open = validator(
        ['password' => 'wachtwoord123', 'password_confirmation' => 'wachtwoord123'],
        ['password' => [...BackofficePassword::constraints(), 'confirmed']],
    );
    expect($open->fails())->toBeFalse();
});

it('toont biometrie op de telefoon zodra het e-mailadres een gekoppelde gsm heeft', function () {
    $tenant = Tenant::factory()->create(['backoffice_phone_login_enabled' => true]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'bio@example.com',
    ]);
    UserPasskeyCredential::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'credential_id' => 'cred-bio',
        'credential_public_key' => base64_encode('key'),
        'user_handle' => 'wp-user-'.$user->id,
        'device_label' => 'Safari op iPhone',
        'sign_count' => 0,
    ]);
    $started = app(StartBackofficeLoginChallengeAction::class)->handle('desk', 'Chrome', null);

    Livewire::test(PhoneLogin::class, ['token' => $started['token']])
        ->assertDontSee(__('auth.phone.biometric'))
        ->set('email', 'bio@example.com')
        ->assertSee(__('auth.phone.biometric'));

    expect(BackofficeLoginChallenge::findByPlainToken($started['token'])?->user_id)->toBe($user->id);
    expect(fn () => app(VerifyUserPasskeyAssertionAction::class)->handle('geen-ticket', '{}', 'localhost', ['http://localhost']))
        ->toThrow(InvalidArgumentException::class, 'expired');
});

it('slaat het ticket over zolang de gebruiker in de tabel nog verplicht is', function () {
    Schema::drop('backoffice_login_challenges');
    Schema::create('backoffice_login_challenges', function (Blueprint $table) {
        $table->id();
        $table->string('token_hash', 64)->unique();
        $table->char('confirmation_code', 3);
        $table->string('session_id');
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('tenant_id');
        $table->string('computer_label');
        $table->string('status', 32);
        $table->timestamp('expires_at');
        $table->timestamps();
    });

    expect(app(StartBackofficeLoginChallengeAction::class)->handle('desk', 'Chrome', null))->toBeNull();
});

it('meldt de computer aan via de telefoon zonder de telefoon een sessie te geven', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'Schoonmaak Noord',
        'backoffice_phone_login_enabled' => true,
        'backoffice_phone_login_decided_at' => now(),
    ]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'beheer@example.com',
        'password' => Hash::make('wachtwoord123'),
    ]);

    expect(app(StartBackofficeLoginChallengeAction::class)->handle('desk', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0', null))->not->toBeNull();

    $off = Tenant::factory()->create(['backoffice_phone_login_enabled' => false]);
    User::factory()->create(['tenant_id' => $off->id, 'email' => 'uit@example.com']);
    $offTicket = app(StartBackofficeLoginChallengeAction::class)->handle('desk-2', 'Chrome', null);
    expect($offTicket)->not->toBeNull()
        ->and(app(SubmitPhonePasswordAction::class)->handle($offTicket['token'], 'uit@example.com', 'wachtwoord123', '127.0.0.1')['ok'])->toBeFalse();

    $started = app(StartBackofficeLoginChallengeAction::class)->handle('desk', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0', null);

    Livewire::test(Login::class)
        ->assertSeeHtml('wp-auth-login-qr')
        ->assertSeeHtml('width="96"');

    Livewire::test(PhoneLogin::class, ['token' => $started['token']])
        ->assertSee(__('auth.email'))
        ->assertSee(__('auth.password'))
        ->assertDontSee('Bevestig met vingerafdruk');

    $wrong = app(SubmitPhonePasswordAction::class)->handle($started['token'], 'beheer@example.com', 'foutwachtwoord', '127.0.0.1');
    expect($wrong['ok'])->toBeFalse()->and(auth()->check())->toBeFalse();

    $otherEmail = app(SubmitPhonePasswordAction::class)->handle($started['token'], 'ander@example.com', 'wachtwoord123', '127.0.0.1');
    expect($otherEmail['ok'])->toBeFalse();

    $right = app(SubmitPhonePasswordAction::class)->handle($started['token'], 'beheer@example.com', 'wachtwoord123', '127.0.0.1');
    expect($right['ok'])->toBeTrue()->and(auth()->check())->toBeFalse();

    expect(app(ConsumeBackofficeLoginChallengeAction::class)->handle('desk'))->toBeNull();
    expect(app(ConfirmPhoneLoginAction::class)->handle($started['token'])['ok'])->toBeTrue();
    expect(app(ConsumeBackofficeLoginChallengeAction::class)->handle('andere-computer'))->toBeNull();

    $loggedIn = app(ConsumeBackofficeLoginChallengeAction::class)->handle('desk');
    expect($loggedIn?->id)->toBe($user->id);
    expect(app(ConsumeBackofficeLoginChallengeAction::class)->handle('desk'))->toBeNull();
});

it('laat een verlopen code niet meer binnen', function () {
    $tenant = Tenant::factory()->create(['backoffice_phone_login_enabled' => true]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $challenge = BackofficeLoginChallenge::query()->create([
        'token_hash' => hash('sha256', 'ticket'),
        'confirmation_code' => '042',
        'session_id' => 'desk',
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'computer_label' => 'Chrome op Windows',
        'status' => BackofficeLoginChallenge::STATUS_APPROVED,
        'expires_at' => now()->subMinute(),
    ]);

    expect($challenge->isOpen())->toBeFalse();
    expect(app(ConsumeBackofficeLoginChallengeAction::class)->handle('desk'))->toBeNull();
});

it('laat alleen een beheerder een gsm vrijgeven en schrijft dat in het auditlog', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'name' => 'Ada Beheer']);
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id, 'name' => 'Mo Medewerker']);
    UserPasskeyCredential::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $employee->id,
        'credential_id' => 'cred-1',
        'credential_public_key' => base64_encode('key'),
        'user_handle' => 'wp-user-'.$employee->id,
        'device_label' => 'Safari op iPhone',
        'sign_count' => 0,
    ]);

    expect($employee->can('releasePhone', $employee))->toBeFalse();
    expect($admin->can('releasePhone', $employee))->toBeTrue();

    app(ReleaseUserPhoneAction::class)->handle($employee, (int) $tenant->id, (int) $admin->id);

    $row = DB::table('audit_logs')->where('action', 'auth.phone_released')->first();
    expect($row)->not->toBeNull()
        ->and((int) $row->user_id)->toBe($admin->id)
        ->and($employee->fresh()->phone_reregistration_open)->toBeTrue()
        ->and($employee->fresh()->phone_revoked_label)->toBe('Safari op iPhone')
        ->and(UserPasskeyCredential::query()->where('user_id', $employee->id)->exists())->toBeFalse();

    $payload = json_decode($row->payload, true);
    expect($payload['user_name'])->toBe('Mo Medewerker')
        ->and($payload['device_label'])->toBe('Safari op iPhone')
        ->and($row->created_at)->not->toBeNull();
});

it('noteert een aanmelding buiten de uren en een reeks nieuwe aanmeldingen', function () {
    $tenant = Tenant::factory()->create([
        'login_alert_enabled' => true,
        'login_alert_start' => '00:00',
        'login_alert_end' => '23:59',
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Piet']);

    app(RecordBackofficeLoginAction::class)->handle($user);
    app(RecordBackofficeLoginAction::class)->handle($user);
    app(RecordBackofficeLoginAction::class)->handle($user);

    expect(DB::table('audit_logs')->where('action', 'auth.login_outside_hours')->count())->toBe(3)
        ->and(DB::table('audit_logs')->where('action', 'auth.session_burst')->count())->toBe(1);

    seedTenantPastOnboarding($tenant);

    $marker = strstr(__('dashboard.security.session_burst', [
        'name' => 'Piet',
        'count' => 3,
        'time' => "\0",
    ]), "\0", true);
    $html = Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSee('Piet')
        ->assertSee(__('dashboard.security.alerts_heading', ['tenant' => $tenant->name]), false)
        ->assertSeeHtml('wp-pill wp-pill--progress')
        ->assertSee($marker, false)
        ->html();
    $intent = strpos($html, __('dashboard.intent.title'));
    $dashboard = strpos($html, __('dashboard.title'));
    $alert = strpos($html, $marker);
    expect($intent)->not->toBeFalse()
        ->and($dashboard)->toBeGreaterThan($intent)
        ->and($alert)->toBeGreaterThan($dashboard);
});

it('stopt een sessie na twaalf uur en toont de kaart alleen aan een beheerder', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->withSession(['auth_logged_in_at' => now()->subHours(EnsureAbsoluteSessionLifetime::HOURS + 1)->timestamp])
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    Livewire::actingAs($employee)->test(Dashboard::class)
        ->assertDontSee(__('dashboard.security.prompt_title'));

    Livewire::actingAs($admin)->test(Dashboard::class)
        ->assertSee(__('dashboard.security.prompt_title'))
        ->call('decidePhoneLogin', false)
        ->assertDontSee(__('dashboard.security.prompt_title'));

    expect($tenant->fresh()->backoffice_phone_login_enabled)->toBeFalse()
        ->and($tenant->fresh()->backoffice_phone_login_decided_at)->not->toBeNull();
});

it('zet aanmelden met de telefoon aan en uit bij de organisatie', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($employee)
        ->test(Settings::class)
        ->set('backofficePhoneLogin', true)
        ->assertForbidden();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->assertSee(__('settings.security.phone_login'))
        ->set('backofficePhoneLogin', true);

    expect($tenant->fresh()->backoffice_phone_login_enabled)->toBeTrue()
        ->and($tenant->fresh()->backoffice_phone_login_decided_at)->not->toBeNull();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('backofficePhoneLogin', false);

    expect($tenant->fresh()->backoffice_phone_login_enabled)->toBeFalse();
});

it('houdt een registratie tegen op een te kort wachtwoord', function () {
    Livewire::test(Register::class)
        ->set('password', 'kortwoord')
        ->set('password_confirmation', 'kortwoord')
        ->call('register')
        ->assertHasErrors(['password']);
});
