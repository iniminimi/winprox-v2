<?php

use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('klokt een worker in via de API met time:write', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['time:write'])->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/time/clock-in', [
            'worker_id' => $worker->id,
            'clock_point_id' => $clockPoint->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.worker_id', $worker->id)
        ->assertJsonPath('data.status', 'open');

    Tenancy::actAs($tenant->id);
    expect(WorkShift::query()->where('worker_id', $worker->id)->open()->exists())->toBeTrue();
});

it('klokt een worker uit via de API met time:write', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);
    app(\App\Actions\Time\ClockInAction::class)->handle($worker, $clockPoint);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['time:write'])->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/time/clock-out', [
            'worker_id' => $worker->id,
            'clock_point_id' => $clockPoint->id,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'closed');
});

it('weigert time write zonder time:write ability', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    $worker = Worker::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['time:read'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/time/clock-in', [
            'worker_id' => $worker->id,
            'clock_point_id' => $clockPoint->id,
        ])
        ->assertForbidden();
});
