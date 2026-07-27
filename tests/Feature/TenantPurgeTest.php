<?php

use App\Actions\TenantPurge\ConfirmTenantPurgeEmailAction;
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
use Illuminate\Support\Facades\Mail;
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
