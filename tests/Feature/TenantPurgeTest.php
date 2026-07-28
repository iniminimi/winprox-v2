<?php

use App\Actions\TenantPurge\ConfirmTenantPurgeEmailAction;
use App\Actions\TenantPurge\CreateTenantPurgeBackupAction;
use App\Actions\TenantPurge\ExecuteTenantPurgeAction;
use App\Actions\TenantPurge\SendTenantPurgeRemindersAction;
use App\Actions\TenantPurge\StartTenantPurgeRequestAction;
use App\Enums\TenantPurgeStatus;
use App\Enums\TenantPurgeTrack;
use App\Mail\TenantPurgeCompletedMail;
use App\Mail\TenantPurgeConfirmMail;
use App\Mail\TenantPurgeReminderMail;
use App\Mail\TenantPurgeScheduledMail;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantPurgeRequest;
use App\Models\User;
use App\Support\Platform\SupportTenantContext;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(function () {
    Tenancy::forget();
    SupportTenantContext::stop();
});

it('start trial purge: stuurt mail naar alle admins en weigert medewerker', function () {
    Mail::fake();

    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(10)]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $admin2 = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $employee = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    expect(fn () => app(StartTenantPurgeRequestAction::class)->handle($tenant, $employee, 'password', true))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $request = app(StartTenantPurgeRequestAction::class)->handle($tenant, $admin, 'password', true);

    expect($request->track)->toBe(TenantPurgeTrack::Trial)
        ->and($request->status)->toBe(TenantPurgeStatus::AwaitingEmail);

    Mail::assertSent(TenantPurgeConfirmMail::class, 2);
});

it('trial purge: e-mailbevestiging dan uitvoeren met backup en resultaatmail', function () {
    Mail::fake();
    Storage::fake('local');
    Storage::fake('public');

    $tenant = Tenant::factory()->create(['name' => 'Trial Co', 'trial_ends_at' => now()->addDays(5)]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    Location::factory()->create(['tenant_id' => $tenant->id]);
    Issue::factory()->create(['tenant_id' => $tenant->id]);

    $plain = 'confirm-token-test-64chars-confirm-token-test-64chars-xxxx';
    $purge = TenantPurgeRequest::query()->create([
        'tenant_id' => $tenant->id,
        'tenant_name' => $tenant->name,
        'track' => TenantPurgeTrack::Trial,
        'status' => TenantPurgeStatus::AwaitingEmail,
        'initiated_by_user_id' => $admin->id,
        'export_acknowledged_at' => now(),
        'password_verified_at' => now(),
        'confirmation_token_hash' => hash('sha256', $plain),
    ]);

    $confirmed = app(ConfirmTenantPurgeEmailAction::class)->handle($purge, $admin, $plain);
    expect($confirmed->status)->toBe(TenantPurgeStatus::Ready)
        ->and($confirmed->email_confirmed_at)->not->toBeNull();

    $done = app(ExecuteTenantPurgeAction::class)->handle($confirmed->fresh(), $admin, 'password');

    expect($done->status)->toBe(TenantPurgeStatus::Completed)
        ->and($done->backup_path)->not->toBeNull()
        ->and($done->backup_expires_at)->not->toBeNull()
        ->and($done->deleted_counts['issues'] ?? 0)->toBe(1)
        ->and(Tenant::query()->whereKey($tenant->id)->exists())->toBeFalse();

    Storage::disk('local')->assertExists($done->backup_path);
    Mail::assertSent(TenantPurgeCompletedMail::class, 1);
});

it('paid purge: plant cool-down, reminder, alleen superuser voert uit', function () {
    Mail::fake();
    Storage::fake('local');
    Storage::fake('public');

    $tenant = Tenant::factory()->create([
        'name' => 'Paid Co',
        'trial_ends_at' => now()->subDay(),
        'billing_plan' => 'facility',
        'billing_active_until' => now()->addMonth(),
    ]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $super = User::factory()->superuser()->create();

    $plain = 'paid-confirm-token-64chars-paid-confirm-token-64chars-yyyyyy';
    $purge = TenantPurgeRequest::query()->create([
        'tenant_id' => $tenant->id,
        'tenant_name' => $tenant->name,
        'track' => TenantPurgeTrack::Paid,
        'status' => TenantPurgeStatus::AwaitingEmail,
        'initiated_by_user_id' => $admin->id,
        'export_acknowledged_at' => now(),
        'password_verified_at' => now(),
        'confirmation_token_hash' => hash('sha256', $plain),
    ]);

    $scheduled = app(ConfirmTenantPurgeEmailAction::class)->handle($purge, $admin, $plain);
    expect($scheduled->status)->toBe(TenantPurgeStatus::Scheduled)
        ->and($scheduled->scheduled_purge_at)->not->toBeNull();
    Mail::assertSent(TenantPurgeScheduledMail::class, 1);

    expect(fn () => app(ExecuteTenantPurgeAction::class)->handle($scheduled->fresh(), $admin))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(fn () => app(ExecuteTenantPurgeAction::class)->handle($scheduled->fresh(), $super))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    // Reminder: schedule at +2 days noon
    $scheduled->scheduled_purge_at = now()->addDays(2)->setTime(12, 0);
    $scheduled->reminder_sent_at = null;
    $scheduled->save();

    $stats = app(SendTenantPurgeRemindersAction::class)->handle(now());
    expect($stats['sent'])->toBe(1);
    Mail::assertSent(TenantPurgeReminderMail::class, 1);

    $scheduled->scheduled_purge_at = now()->subMinute();
    $scheduled->save();

    $done = app(ExecuteTenantPurgeAction::class)->handle($scheduled->fresh(), $super);
    expect($done->status)->toBe(TenantPurgeStatus::Completed)
        ->and(Tenant::query()->whereKey($tenant->id)->exists())->toBeFalse();
});

it('subscription livewire toont purge sectie voor admin op trial', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(8)]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Pages\Subscription::class)
        ->assertSee(__('subscription.purge.title'))
        ->assertSee(__('subscription.purge.start'));
});

it('purge start: toont veldfouten zonder modal bij ontbrekende gegevens', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(8)]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Pages\Subscription::class)
        ->call('preparePurgeConfirm', 'start')
        ->assertHasErrors(['purge_password', 'purge_export_ack'])
        ->assertSet('purgeConfirmKind', null);
});

it('purge start: opent wp-modal bevestiging na geldige velden', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(8)]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Pages\Subscription::class)
        ->set('purgeExportAck', true)
        ->set('purgePassword', 'password')
        ->call('preparePurgeConfirm', 'start')
        ->assertHasNoErrors()
        ->assertSet('purgeConfirmKind', 'start')
        ->assertSee(__('subscription.purge.confirm_start'));
});

it('purge start: toont fout bij verkeerd wachtwoord zonder modal', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(8)]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Pages\Subscription::class)
        ->set('purgeExportAck', true)
        ->set('purgePassword', 'verkeerd-wachtwoord')
        ->call('preparePurgeConfirm', 'start')
        ->assertHasErrors(['purge_password'])
        ->assertSet('purgeConfirmKind', null)
        ->assertSee(__('subscription.purge.errors.password'));
});

it('purge start: logt uit na drie foute wachtwoordpogingen', function () {
    config(['tenant_purge.password_max_attempts' => 3]);

    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(8)]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $component = Livewire::actingAs($admin)
        ->test(\App\Livewire\Pages\Subscription::class)
        ->set('purgeExportAck', true)
        ->set('purgePassword', 'verkeerd-1')
        ->call('preparePurgeConfirm', 'start')
        ->assertHasErrors(['purge_password']);

    expect(auth()->check())->toBeTrue();

    $component
        ->set('purgePassword', 'verkeerd-2')
        ->call('preparePurgeConfirm', 'start')
        ->assertHasErrors(['purge_password']);

    expect(auth()->check())->toBeTrue();

    $component
        ->set('purgePassword', 'verkeerd-3')
        ->call('preparePurgeConfirm', 'start')
        ->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse()
        ->and(session('error'))->toBe(__('subscription.purge.errors.too_many_password_attempts'));
});

it('trial purge laat geen tenant_id referenties achter in tenant-scoped tabellen', function () {
    Storage::fake('local');
    Storage::fake('public');

    $tenant = Tenant::factory()->create(['name' => 'Cleanup Co', 'trial_ends_at' => now()->addDays(5)]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    Location::factory()->create(['tenant_id' => $tenant->id]);
    Issue::factory()->create(['tenant_id' => $tenant->id]);

    DB::table('audit_logs')->insert([
        'tenant_id' => $tenant->id,
        'user_id' => $admin->id,
        'action' => 'tenant_purge.test',
        'model_type' => Tenant::class,
        'model_id' => $tenant->id,
        'payload' => json_encode(['check' => true], JSON_THROW_ON_ERROR),
        'created_at' => now(),
    ]);

    DB::table('contact_messages')->insert([
        'message_id' => 'purge-test-'.$tenant->id,
        'name' => 'Tenant Admin',
        'email' => 'admin@example.com',
        'subject' => 'Purge test',
        'message' => 'Check tenant cleanup.',
        'direction' => 'inbound',
        'tenant_id' => $tenant->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $purge = TenantPurgeRequest::query()->create([
        'tenant_id' => $tenant->id,
        'tenant_name' => $tenant->name,
        'track' => TenantPurgeTrack::Trial,
        'status' => TenantPurgeStatus::Ready,
        'initiated_by_user_id' => $admin->id,
        'export_acknowledged_at' => now(),
        'password_verified_at' => now(),
        'email_confirmed_at' => now(),
        'email_confirmed_by_user_id' => $admin->id,
    ]);

    app(ExecuteTenantPurgeAction::class)->handle($purge, $admin, 'password');

    $tables = collect(Schema::getTableListing())
        ->filter(fn (string $table): bool => Schema::hasColumn($table, 'tenant_id'))
        ->values();

    foreach ($tables as $table) {
        $count = (int) DB::table($table)->where('tenant_id', $tenant->id)->count();
        expect($count)->toBe(0, 'Tenant reference remained in '.$table);
    }
});

it('tenant purge backup bevat tenant en tenant-scoped data-rows', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create(['name' => 'Backup Co', 'trial_ends_at' => now()->addDays(7)]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Issue::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);

    $purge = TenantPurgeRequest::query()->create([
        'tenant_id' => $tenant->id,
        'tenant_name' => $tenant->name,
        'track' => TenantPurgeTrack::Trial,
        'status' => TenantPurgeStatus::Ready,
        'initiated_by_user_id' => $admin->id,
        'export_acknowledged_at' => now(),
        'password_verified_at' => now(),
        'email_confirmed_at' => now(),
        'email_confirmed_by_user_id' => $admin->id,
    ]);

    $backupPath = app(CreateTenantPurgeBackupAction::class)->handle($tenant, $purge);
    $raw = Storage::disk('local')->get($backupPath);
    $sql = gzdecode($raw);

    expect($sql)->not->toBeFalse()
        ->and($sql)->toContain('INSERT INTO `tenants`')
        ->and($sql)->toMatch('/INSERT INTO `(?:main\\.)?issues`/')
        ->and($sql)->toMatch('/INSERT INTO `(?:main\\.)?locations`/');
});
