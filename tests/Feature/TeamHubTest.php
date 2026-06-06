<?php

use App\Livewire\Auth\Login;
use App\Livewire\Pages\Settings;
use App\Livewire\Pages\Team;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Support\Tenancy;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{0: Tenant, 1: User}
 */
function tenantWithAdmin(): array
{
    $tenant = Tenant::factory()->create(['name' => 'Acme NV']);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    return [$tenant, $admin];
}

// --- Collega-gebruikers (admin) -------------------------------------------

it('laat een admin een collega-gebruiker aanmaken met set-wachtwoord-mail', function () {
    Notification::fake();
    [$tenant, $admin] = tenantWithAdmin();

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openCreateColleague')
        ->set('colleagueName', 'Nieuwe Collega')
        ->set('colleagueEmail', 'collega@acme.test')
        ->set('colleagueLocale', 'nl')
        ->set('colleagueRole', User::ROLE_EMPLOYEE)
        ->set('colleaguePassword', 'wachtwoord123')
        ->set('colleaguePasswordConfirmation', 'wachtwoord123')
        ->set('colleagueSendAccountEmail', true)
        ->call('saveColleague')
        ->assertHasNoErrors();

    $user = User::where('email', 'collega@acme.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->tenant_id)->toBe($tenant->id)
        ->and($user->role)->toBe(User::ROLE_EMPLOYEE)
        ->and($user->locale)->toBe('nl')
        ->and($user->is_active)->toBeTrue();

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('stuurt geen accountmail wanneer de checkbox uit staat', function () {
    Notification::fake();
    [, $admin] = tenantWithAdmin();

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openCreateColleague')
        ->set('colleagueName', 'Zonder Mail')
        ->set('colleagueEmail', 'zonder-mail@acme.test')
        ->set('colleagueLocale', 'en')
        ->set('colleagueRole', User::ROLE_EMPLOYEE)
        ->set('colleaguePassword', 'wachtwoord123')
        ->set('colleaguePasswordConfirmation', 'wachtwoord123')
        ->set('colleagueSendAccountEmail', false)
        ->call('saveColleague')
        ->assertHasNoErrors();

    $user = User::where('email', 'zonder-mail@acme.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->locale)->toBe('en');

    Notification::assertNothingSent();
});

it('laat een admin een collega bewerken en deactiveren', function () {
    [$tenant, $admin] = tenantWithAdmin();
    $colleague = User::factory()->employee()->create(['tenant_id' => $tenant->id, 'name' => 'Oud']);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openEditColleague', $colleague->id)
        ->set('colleagueName', 'Nieuw')
        ->set('colleagueEmail', $colleague->email)
        ->set('colleagueLocale', 'fr')
        ->set('colleagueRole', User::ROLE_ADMIN)
        ->call('saveColleague')
        ->assertHasNoErrors();

    expect($colleague->fresh()->name)->toBe('Nieuw')
        ->and($colleague->fresh()->role)->toBe(User::ROLE_ADMIN)
        ->and($colleague->fresh()->locale)->toBe('fr');

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('setColleagueActive', $colleague->id, false);

    expect($colleague->fresh()->is_active)->toBeFalse();
});

it('verbergt de collega-sectie voor een medewerker op gebruikers', function () {
    [$tenant] = tenantWithAdmin();
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($employee)
        ->test(Team::class)
        ->assertDontSee(__('team.colleagues.title'), false)
        ->assertDontSee(__('team.colleagues.invite_hint'), false)
        ->assertSee(__('team.teams.title'));
});

it('weigert dat een medewerker collega-gebruikers beheert', function () {
    [$tenant] = tenantWithAdmin();
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($employee)
        ->test(Team::class)
        ->call('openCreateColleague')
        ->assertForbidden();
});

it('laat een admin bedrijfsgegevens aanpassen via instellingen', function () {
    [$tenant, $admin] = tenantWithAdmin();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('orgName', 'Acme Holding')
        ->set('orgEmail', 'info@acme.test')
        ->set('orgPhone', '+32 50 00 00 00')
        ->set('orgStreet', 'Bosrandstraat')
        ->set('orgHouseNumber', '10')
        ->set('orgPostalCode', '8000')
        ->set('orgCity', 'Brugge')
        ->set('orgCountryCode', 'be')
        ->call('saveOrganisation')
        ->assertHasNoErrors();

    $fresh = $tenant->fresh();
    expect($fresh->name)->toBe('Acme Holding')
        ->and($fresh->email)->toBe('info@acme.test')
        ->and($fresh->phone)->toBe('+32 50 00 00 00')
        ->and($fresh->street)->toBe('Bosrandstraat')
        ->and($fresh->house_number)->toBe('10')
        ->and($fresh->postal_code)->toBe('8000')
        ->and($fresh->city)->toBe('Brugge')
        ->and($fresh->country_code)->toBe('BE');
});

it('laat een medewerker instellingen zien maar niet bedrijfsgegevens bewerken', function () {
    [$tenant] = tenantWithAdmin();
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($employee)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee(__('settings.org.readonly_hint'), false);

    Livewire::actingAs($employee)
        ->test(Settings::class)
        ->set('orgName', 'Hacked')
        ->call('saveOrganisation')
        ->assertForbidden();

    expect($tenant->fresh()->name)->not->toBe('Hacked');
});

it('laat een medewerker de WinProx-stijl aanpassen', function () {
    [$tenant] = tenantWithAdmin();
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($employee)
        ->test(Settings::class)
        ->set('uiTheme', 'dark')
        ->assertHasNoErrors();

    expect($employee->fresh()->ui_theme)->toBe('dark');
});

// --- Login-afdwinging ------------------------------------------------------

it('weigert login voor een gedeactiveerde gebruiker', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->inactive()->create([
        'tenant_id' => $tenant->id,
        'password' => Hash::make('geheim123'),
    ]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'geheim123')
        ->call('login')
        ->assertHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('laat login toe voor een actieve gebruiker', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'password' => Hash::make('geheim123'),
    ]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'geheim123')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect(auth()->check())->toBeTrue();
});

// --- Teams: RBAC -----------------------------------------------------------

it('staat teambeheer-rollen correct toe (admin alles, medewerker enkel inhoud)', function () {
    [$tenant, $admin] = tenantWithAdmin();
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    expect(Gate::forUser($admin)->allows('create', InternalTeam::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('deactivate', $team))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('update', $team))->toBeTrue()
        ->and(Gate::forUser($employee)->allows('create', InternalTeam::class))->toBeFalse()
        ->and(Gate::forUser($employee)->allows('deactivate', $team))->toBeFalse()
        ->and(Gate::forUser($employee)->allows('update', $team))->toBeTrue();
});

it('laat een admin een team aanmaken en deactiveren', function () {
    [$tenant, $admin] = tenantWithAdmin();

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openCreateTeam')
        ->set('teamName', 'Technische dienst')
        ->set('teamSortOrder', 3)
        ->call('saveTeam')
        ->assertHasNoErrors();

    $team = InternalTeam::where('name', 'Technische dienst')->first();
    expect($team)->not->toBeNull()
        ->and($team->sort_order)->toBe(3)
        ->and($team->field_qr_token)->not->toBeEmpty();

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('setTeamActive', $team->id, false);

    expect($team->fresh()->is_active)->toBeFalse();
});

it('laat een medewerker teaminhoud bewerken maar niet aanmaken/deactiveren', function () {
    [$tenant] = tenantWithAdmin();
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Schoonmaak']);

    Livewire::actingAs($employee)
        ->test(Team::class)
        ->call('openEditTeam', $team->id)
        ->set('teamName', 'Schoonmaak Plus')
        ->call('saveTeam')
        ->assertHasNoErrors();

    expect($team->fresh()->name)->toBe('Schoonmaak Plus');

    Livewire::actingAs($employee)
        ->test(Team::class)
        ->call('openCreateTeam')
        ->assertForbidden();

    Livewire::actingAs($employee)
        ->test(Team::class)
        ->call('setTeamActive', $team->id, false)
        ->assertForbidden();

    expect($team->fresh()->is_active)->toBeTrue();
});

it('toont de team-QR die naar het publieke team-portaal linkt', function () {
    [$tenant, $admin] = tenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('team.qr', $team))
        ->assertOk()
        ->assertSee('<svg', false); // Check for QR code SVG
});

// --- Workers ---------------------------------------------------------------

it('laat een team-beheerder een worker toevoegen', function () {
    [$tenant, $admin] = tenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openAddWorker', $team->id)
        ->set('workerFirstName', 'Sven')
        ->set('workerLastName', 'Peeters')
        ->call('saveWorker')
        ->assertHasNoErrors();

    $worker = Worker::where('first_name', 'Sven')->first();
    expect($worker)->not->toBeNull()
        ->and($worker->internal_team_id)->toBe($team->id)
        ->and($worker->is_teamleader)->toBeFalse();
});

it('wisselt de teamleader-vlag van een worker', function () {
    [$tenant, $admin] = tenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);

    Livewire::actingAs($admin)->test(Team::class)->call('setWorkerTeamleader', $worker->id, true);
    expect($worker->fresh()->is_teamleader)->toBeTrue();

    Livewire::actingAs($admin)->test(Team::class)->call('setWorkerTeamleader', $worker->id, false);
    expect($worker->fresh()->is_teamleader)->toBeFalse();
});

it('reset het icoon: wist slug, pogingen, lockout en devices', function () {
    [$tenant, $admin] = tenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->withIcon('star')->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'field_icon_failed_attempts' => 2,
        'field_icon_locked_at' => now(),
    ]);
    WorkerDevice::factory()->create(['tenant_id' => $tenant->id, 'worker_id' => $worker->id]);

    expect($worker->devices()->count())->toBe(1);

    Livewire::actingAs($admin)->test(Team::class)->call('resetWorkerIcon', $worker->id);

    $fresh = $worker->fresh();
    expect($fresh->field_icon_slug)->toBeNull()
        ->and($fresh->field_icon_failed_attempts)->toBe(0)
        ->and($fresh->field_icon_locked_at)->toBeNull()
        ->and($fresh->devices()->count())->toBe(0);
});

it('wisselt de actief-vlag van een worker en verwijdert een worker', function () {
    [$tenant, $admin] = tenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);

    Livewire::actingAs($admin)->test(Team::class)->call('setWorkerActive', $worker->id, false);
    expect($worker->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($admin)->test(Team::class)->call('deleteWorker', $worker->id);
    expect(Worker::find($worker->id))->toBeNull();
});

// --- Tenant-isolatie -------------------------------------------------------

it('toont geen teams of gebruikers van een andere tenant', function () {
    $tenantB = Tenant::factory()->create(['name' => 'Andere BV']);
    Tenancy::actAs($tenantB->id);
    InternalTeam::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Geheim Team B']);
    User::factory()->employee()->create(['tenant_id' => $tenantB->id, 'name' => 'Bob van B', 'email' => 'bob@b.test']);

    [$tenantA, $adminA] = tenantWithAdmin();
    InternalTeam::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Team A']);

    Livewire::actingAs($adminA)
        ->test(Team::class)
        ->assertSee('Team A')
        ->assertDontSee('Geheim Team B')
        ->assertDontSee('bob@b.test');
});
