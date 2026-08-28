<?php

use App\Livewire\Auth\Login;
use App\Livewire\Pages\Settings;
use App\Livewire\Pages\Team;
use App\Models\InternalTeam;
use App\Models\InternalTeamTranslation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Support\Tenancy;
use App\Mail\WelcomeAccountMail;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('toont de briefing-afdrukknop op de teamspagina', function () {
    [, $admin] = tenantWithAdmin();

    $this->actingAs($admin)
        ->get(route('team.index', ['section' => 'teams']))
        ->assertOk()
        ->assertSee(__('team.briefing'), false)
        ->assertSee(route('briefing.print'), false)
        ->assertDontSee(__('team.colleagues.title'), false);
});

it('toont geen briefing op backoffice', function () {
    [, $admin] = tenantWithAdmin();

    $this->actingAs($admin)
        ->get(route('team.index', ['section' => 'backoffice']))
        ->assertOk()
        ->assertSee(__('team.colleagues.title'), false)
        ->assertDontSee(__('team.briefing'), false);
});

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
    Mail::fake();
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

    Mail::assertSent(WelcomeAccountMail::class, fn (WelcomeAccountMail $mail): bool => $mail->hasTo('collega@acme.test'));
});

it('stuurt geen accountmail wanneer de checkbox uit staat', function () {
    Mail::fake();
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

    Mail::assertNothingSent();
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
        ->assertSee(__('team.teams.title'));
});

it('weigert dat een medewerker collega-gebruikers beheert', function () {
    [$tenant] = tenantWithAdmin();
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($employee)
        ->test(Team::class)
        ->call('openCreateColleague')
        ->assertHasErrors(['colleagueCreate']);
});

it('toont gelokaliseerde bestandskiezer op instellingen', function () {
    [$tenant, $admin] = tenantWithAdmin();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->assertSee(__('common.file.browse'), false)
        ->assertSee(__('common.file.none_selected'), false)
        ->assertSee('wp-settings-section', false)
        ->assertSee(':aria-expanded="open"', false);
});

it('opent het organisatie-modal zonder qr-sticker preview in livewire state', function () {
    [$tenant, $admin] = tenantWithAdmin();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('openOrgModal')
        ->assertSet('showOrgModal', true)
        ->assertSet('qrStickerPreviewDataUrl', null);
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

it('behoudt het organisatielogo bij het opslaan van bedrijfsgegevens', function () {
    Storage::fake('public');
    [$tenant, $admin] = tenantWithAdmin();

    $logoPath = UploadedFile::fake()->image('logo.png')->store("tenant-logos/{$tenant->id}", 'public');
    $tenant->update(['logo_path' => $logoPath]);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('orgName', 'Acme Holding')
        ->set('orgEmail', 'info@acme.test')
        ->call('saveOrganisation')
        ->assertHasNoErrors();

    $fresh = $tenant->fresh();
    expect($fresh->logo_path)->toBe($logoPath)
        ->and($fresh->name)->toBe('Acme Holding')
        ->and($fresh->email)->toBe('info@acme.test');
});

it('behoudt bedrijfsgegevens bij het opslaan van het organisatielogo', function () {
    Storage::fake('public');
    [$tenant, $admin] = tenantWithAdmin();

    $tenant->update([
        'email' => 'info@acme.test',
        'phone' => '+32 50 00 00 00',
        'street' => 'Bosrandstraat',
        'house_number' => '10',
        'postal_code' => '8000',
        'city' => 'Brugge',
        'country_code' => 'BE',
    ]);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('orgLogo', UploadedFile::fake()->image('nieuw-logo.png'))
        ->assertHasNoErrors();

    $fresh = $tenant->fresh();
    expect($fresh->logo_path)->not->toBeNull()
        ->and($fresh->email)->toBe('info@acme.test')
        ->and($fresh->phone)->toBe('+32 50 00 00 00')
        ->and($fresh->street)->toBe('Bosrandstraat')
        ->and($fresh->city)->toBe('Brugge');
});

it('schakelt custom theme in bij het opslaan van portaalkleuren', function () {
    [$tenant] = tenantWithAdmin();
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    $tenant->update([
        'custom_theme_active' => false,
        'custom_theme_bg' => '#e7e8ec',
        'custom_theme_btn' => '#059669',
    ]);

    Livewire::actingAs($employee)
        ->test(Settings::class)
        ->set('customThemeBg', '#112233')
        ->set('customThemeBtn', '#445566')
        ->call('saveOrganisationInline')
        ->assertHasNoErrors();

    $fresh = $tenant->fresh();
    expect($fresh->custom_theme_active)->toBeTrue()
        ->and($fresh->custom_theme_bg)->toBe('#112233')
        ->and($fresh->custom_theme_btn)->toBe('#445566');
});

it('behoudt portaalkleuren bij het opslaan van organisatiegegevens via modal', function () {
    [$tenant, $admin] = tenantWithAdmin();
    $tenant->update([
        'custom_theme_active' => true,
        'custom_theme_bg' => '#aabbcc',
        'custom_theme_btn' => '#ddeeff',
    ]);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('orgName', 'Acme Updated')
        ->call('saveOrganisation')
        ->assertHasNoErrors();

    $fresh = $tenant->fresh();
    expect($fresh->name)->toBe('Acme Updated')
        ->and($fresh->custom_theme_active)->toBeTrue()
        ->and($fresh->custom_theme_bg)->toBe('#aabbcc')
        ->and($fresh->custom_theme_btn)->toBe('#ddeeff');
});

it('laat een medewerker een portaal-achtergrond uploaden', function () {
    Storage::fake('public');
    [$tenant] = tenantWithAdmin();
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($employee)
        ->test(Settings::class)
        ->set('portalBackground', UploadedFile::fake()->image('portal-bg.jpg'))
        ->assertHasNoErrors();

    expect($tenant->fresh()->portal_background_path)->not->toBeNull();
});

it('staat avif toe als livewire preview-mime voor portaal-achtergrond', function () {
    expect(config('livewire.temporary_file_upload.preview_mimes'))->toContain('avif');
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

it('laat een medewerker branding-instellingen zien en aanpassen maar niet privacy-export', function () {
    [$tenant] = tenantWithAdmin();
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($employee)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee(__('settings.org.details_label'), false)
        ->assertSee(__('settings.org.logo_label'), false)
        ->assertSee(__('settings.org.portal_background_label'), false)
        ->assertSee(__('settings.org.portal_colors_label'), false)
        ->assertSee(__('settings.qr_stickers.title'), false)
        ->assertDontSee(__('settings.privacy.title'), false);

    Livewire::actingAs($employee)
        ->test(Settings::class)
        ->set('qrStickerAvery6289HeaderText', 'Scan voor meldingen')
        ->call('saveQrStickerAvery6289Settings')
        ->assertHasNoErrors();

    expect($tenant->fresh()->qrStickerSheetSetting(\App\Support\Qr\QrStickerSheetTemplate::Avery62x89R)?->header_text)
        ->toBe('Scan voor meldingen');

    $this->actingAs($employee)
        ->get(route('account.data-export'))
        ->assertForbidden();
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
        ->and(Gate::forUser($admin)->allows('delete', $team))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('update', $team))->toBeTrue()
        ->and(Gate::forUser($employee)->allows('create', InternalTeam::class))->toBeFalse()
        ->and(Gate::forUser($employee)->allows('deactivate', $team))->toBeFalse()
        ->and(Gate::forUser($employee)->allows('delete', $team))->toBeFalse()
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
        ->and($team->sort_order)->toBe(3);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('setTeamActive', $team->id, false);

    expect($team->fresh()->is_active)->toBeFalse();
});

it('laat een admin een leeg team verwijderen', function () {
    [, $admin] = tenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => Tenancy::id(), 'name' => 'Per ongeluk']);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->assertSee(__('common.button.delete'))
        ->call('deleteTeam', $team->id)
        ->assertHasNoErrors();

    expect(InternalTeam::find($team->id))->toBeNull();
});

it('toont geen verwijderknop voor een team met uitvoerders', function () {
    [$tenant, $admin] = tenantWithAdmin();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->assertDontSeeHtml('wire:click="deleteTeam('.$team->id.')"');
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

    Livewire::actingAs($employee)
        ->test(Team::class)
        ->call('deleteTeam', $team->id)
        ->assertForbidden();

    expect($team->fresh()->is_active)->toBeTrue();
});

it('laat een admin een teamvertaling opslaan vanuit bewerken', function () {
    [$tenant, $admin] = tenantWithAdmin();
    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Technische ploeg',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openEditTeam', $team->id)
        ->set('teamPreviewLocale', 'en')
        ->set('teamTranslationName', 'Technical team')
        ->call('saveTeamTranslationOverride')
        ->assertHasNoErrors();

    $translation = InternalTeamTranslation::query()
        ->where('internal_team_id', $team->id)
        ->where('locale', 'en')
        ->first();

    expect($translation)->not->toBeNull()
        ->and($translation?->name)->toBe('Technical team');
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

it('ververst de teamlijst direct na terugdraaien van een worker CSV-import', function () {
    [$tenant, $admin] = tenantWithAdmin();

    $csvPath = tempnam(sys_get_temp_dir(), 'workers_team_test_') . '.csv';
    $handle = fopen($csvPath, 'w');
    fputcsv($handle, ['team_name', 'first_name', 'last_name']);
    fputcsv($handle, ['Import Team', 'Jan', 'Janssen']);
    fputcsv($handle, ['Import Team', 'Piet', 'Pieters']);
    fclose($handle);

    $importDto = new \App\Data\Workers\ImportWorkersData(filePath: $csvPath, originalName: 'workers.csv');
    $importResult = app(\App\Actions\Workers\ImportWorkersAction::class)->handle($importDto, $tenant->id, $admin->id);

    expect($importResult['success'])->toBeTrue();

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->assertSee('Import Team')
        ->call('deleteWorkerImportBatch', $importResult['batch_id'])
        ->assertDontSee('Import Team')
        ->assertSet('workersImportNoticeType', 'success');

    expect(InternalTeam::where('tenant_id', $tenant->id)->where('name', 'Import Team')->exists())->toBeFalse();

    unlink($csvPath);
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
