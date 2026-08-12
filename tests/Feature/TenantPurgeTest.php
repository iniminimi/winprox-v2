<?php

use App\Actions\TenantPurge\ConfirmTenantPurgeEmailAction;
use App\Actions\TenantPurge\CreateTenantPurgeBackupAction;
use App\Actions\TenantPurge\ExecuteTenantPurgeAction;
use App\Actions\TenantPurge\PruneExpiredTenantPurgeBackupsAction;
use App\Actions\TenantPurge\SendTenantPurgeRemindersAction;
use App\Actions\TenantPurge\StartTenantPurgeRequestAction;
use App\Enums\TenantPurgeStatus;
use App\Enums\TenantPurgeTrack;
use App\Mail\TenantPurgeCompletedMail;
use App\Mail\TenantPurgeConfirmMail;
use App\Mail\TenantPurgeExpiredTrialWarningMail;
use App\Mail\TenantPurgeReminderMail;
use App\Mail\TenantPurgeScheduledMail;
use App\Mail\TenantPurgeScheduledToOpsMail;
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
        'billing_plan' => 'facility_100',
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
    Mail::assertSent(TenantPurgeScheduledToOpsMail::class, 1);

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

it('prune verwijdert verlopen backupbestanden zonder DB-row', function () {
    Storage::fake('local');
    config(['tenant_purge.backup_directory' => 'tenant-purge-backups', 'tenant_purge.backup_retention_days' => 30]);

    $path = 'tenant-purge-backups/tenant-99-purge-1-20250701000000.sql.gz';
    Storage::disk('local')->put($path, gzencode('-- test backup', 9));

    $stats = app(PruneExpiredTenantPurgeBackupsAction::class)->handle(
        dryRun: false,
        now: now()->addDays(40),
    );

    expect($stats['deleted'])->toBeGreaterThan(0);
    Storage::disk('local')->assertMissing($path);
});

it('expired trial: plant purge op T+7, reminder T-2, voert uit op T+14', function () {
    Mail::fake();
    Storage::fake('local');
    Storage::fake('public');

    config([
        'tenant_purge.expired_trial_warning_days' => 7,
        'tenant_purge.expired_trial_purge_days' => 14,
        'tenant_purge.reminder_days_before' => 2,
    ]);

    $tenant = Tenant::factory()->create([
        'name' => 'Expired Trial Co',
        'trial_ends_at' => now()->subDays(7)->startOfDay(),
        'billing_plan' => null,
        'billing_active_until' => null,
        'is_active' => true,
    ]);
    $admin = User::factory()->admin()->create([
        'tenant_id' => $tenant->id,
        'locale' => 'nl',
    ]);
    $admin2 = User::factory()->admin()->create([
        'tenant_id' => $tenant->id,
        'locale' => 'en',
    ]);
    User::factory()->employee()->create(['tenant_id' => $tenant->id]);
    Location::factory()->create(['tenant_id' => $tenant->id]);

    expect($tenant->isExpiredTrialWithoutSubscription())->toBeTrue()
        ->and($tenant->hasFullAppAccess())->toBeFalse();

    $scheduleStats = app(\App\Actions\TenantPurge\ScheduleExpiredTrialPurgesAction::class)->handle(now());
    expect($scheduleStats['scheduled'])->toBe(1);

    $purge = TenantPurgeRequest::query()->where('tenant_id', $tenant->id)->first();
    expect($purge)->not->toBeNull()
        ->and($purge->track)->toBe(TenantPurgeTrack::ExpiredTrial)
        ->and($purge->status)->toBe(TenantPurgeStatus::Scheduled)
        ->and($purge->scheduled_purge_at->toDateString())
        ->toBe($tenant->trial_ends_at->copy()->addDays(14)->toDateString());

    Mail::assertSent(TenantPurgeExpiredTrialWarningMail::class, 2);
    Mail::assertSent(TenantPurgeScheduledToOpsMail::class, function (TenantPurgeScheduledToOpsMail $mail) {
        return $mail->hasTo(config('tenant_purge.ops_notification_email'));
    });

    // Reminder window T-2
    $purge->scheduled_purge_at = now()->addDays(2)->setTime(12, 0);
    $purge->reminder_sent_at = null;
    $purge->save();

    $reminderStats = app(SendTenantPurgeRemindersAction::class)->handle(now());
    expect($reminderStats['sent'])->toBe(1);
    Mail::assertSent(TenantPurgeReminderMail::class, 2);

    $purge->scheduled_purge_at = now()->subMinute();
    $purge->save();

    $execStats = app(\App\Actions\TenantPurge\ExecuteDueExpiredTrialPurgesAction::class)->handle(now());
    expect($execStats['executed'])->toBe(1)
        ->and(Tenant::query()->whereKey($tenant->id)->exists())->toBeFalse();

    Mail::assertSent(TenantPurgeCompletedMail::class, 2);
});

it('expired trial: abonnement annuleert openstaande auto-purge', function () {
    Mail::fake();

    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->subDays(10),
        'billing_plan' => null,
        'billing_active_until' => null,
    ]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $purge = TenantPurgeRequest::query()->create([
        'tenant_id' => $tenant->id,
        'tenant_name' => $tenant->name,
        'track' => TenantPurgeTrack::ExpiredTrial,
        'status' => TenantPurgeStatus::Scheduled,
        'scheduled_purge_at' => now()->addDays(4),
    ]);

    app(\App\Actions\Billing\ActivateSubscriptionPlanAction::class)
        ->handle($admin, $tenant, 'facility', 'manual');

    expect($purge->fresh()->status)->toBe(TenantPurgeStatus::Cancelled)
        ->and($tenant->fresh()->hasFullAppAccess())->toBeTrue();
});

it('expired trial: plant niet opnieuw na annulering door admin', function () {
    Mail::fake();
    config([
        'tenant_purge.expired_trial_warning_days' => 7,
        'tenant_purge.expired_trial_purge_days' => 14,
    ]);

    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->subDays(8),
        'billing_plan' => null,
        'billing_active_until' => null,
    ]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    TenantPurgeRequest::query()->create([
        'tenant_id' => $tenant->id,
        'tenant_name' => $tenant->name,
        'track' => TenantPurgeTrack::ExpiredTrial,
        'status' => TenantPurgeStatus::Cancelled,
        'scheduled_purge_at' => now()->addDays(3),
    ]);

    $stats = app(\App\Actions\TenantPurge\ScheduleExpiredTrialPurgesAction::class)->handle(now());
    expect($stats['scheduled'])->toBe(0)
        ->and(TenantPurgeRequest::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', TenantPurgeStatus::Scheduled)
            ->exists())->toBeFalse();

    // Sanity: admin cancel path still authorized for open expired-trial
    $open = TenantPurgeRequest::query()->create([
        'tenant_id' => $tenant->id,
        'tenant_name' => $tenant->name,
        'track' => TenantPurgeTrack::ExpiredTrial,
        'status' => TenantPurgeStatus::Scheduled,
        'scheduled_purge_at' => now()->addDays(3),
    ]);
    app(\App\Actions\TenantPurge\CancelTenantPurgeRequestAction::class)->handle($open, $admin);
    expect($open->fresh()->status)->toBe(TenantPurgeStatus::Cancelled);
});

it('na verlopen trial blijft subscription-route bereikbaar', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->subDay(),
        'billing_plan' => null,
        'billing_active_until' => null,
        'is_active' => true,
    ]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('subscription.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('subscription.index'));
});
