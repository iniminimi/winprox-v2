<?php

use App\Enums\ClockSource;
use App\Enums\WorkShiftStatus;
use App\Livewire\Public\TimePortal;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('laadt een open shift met legacy bluetooth-bron zonder te crashen', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Iris',
        'last_name' => 'Lowyck',
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_token' => 'ble-legacy-'.$tenant->id,
    ]);

    $shift = WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $clockPoint->id,
        'presence_clock_point_id' => $clockPoint->id,
        'status' => WorkShiftStatus::Open,
        'clock_in_at' => now()->subHour(),
        'clock_out_at' => null,
        'clock_in_source' => ClockSource::ClockPointQr,
    ]);

    DB::table('work_shifts')->where('id', $shift->id)->update(['clock_in_source' => 'bluetooth']);

    $loaded = WorkShift::query()->findOrFail($shift->id);
    expect($loaded->clock_in_source)->toBe(ClockSource::Bluetooth);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Iris')
        ->set('last_name', 'Lowyck')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->assertOk()
        ->assertSee(__('time.portal.now.status_in', [
            'time' => $loaded->clock_in_at->format('H:i'),
        ]));
});

it('herschrijft legacy bluetooth-bronnen naar clock_point_qr', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $shift = WorkShift::factory()->closed()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $clockPoint->id,
        'presence_clock_point_id' => $clockPoint->id,
        'clock_in_source' => ClockSource::ClockPointQr,
        'clock_out_source' => ClockSource::ClockPointQr,
    ]);

    DB::table('work_shifts')->where('id', $shift->id)->update([
        'clock_in_source' => 'bluetooth',
        'clock_out_source' => 'bluetooth',
    ]);

    $migration = require database_path('migrations/2026_11_19_104500_remap_legacy_bluetooth_clock_sources.php');
    $migration->up();

    $row = DB::table('work_shifts')->where('id', $shift->id)->first();
    expect($row->clock_in_source)->toBe('clock_point_qr')
        ->and($row->clock_out_source)->toBe('clock_point_qr');
});
