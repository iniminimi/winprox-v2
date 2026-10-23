<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

it('toont de eigen organisatie-logs aan een admin', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    AuditLog::create([
        'user_id' => $admin->id,
        'tenant_id' => $tenant->id,
        'action' => 'work_shift.force_closed',
        'model_type' => \App\Models\WorkShift::class,
        'model_id' => 42,
        'payload' => ['reason' => 'Werknemer vergeten uit te klokken'],
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('activity.index'))
        ->assertOk()
        ->assertSee(__('audit.actions')['work_shift.force_closed'], false)
        ->assertSee('Werknemer vergeten uit te klokken', false);
});

it('toont geen logs van een andere tenant', function () {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    AuditLog::create([
        'user_id' => null,
        'tenant_id' => $other->id,
        'action' => 'work_shift.force_closed',
        'model_type' => \App\Models\WorkShift::class,
        'model_id' => 7,
        'payload' => ['reason' => 'Andere tenant reden'],
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('activity.index'))
        ->assertOk()
        ->assertDontSee('Andere tenant reden', false);
});

it('weigert een niet-admin het activiteitenlog', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $employee = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_EMPLOYEE,
    ]);

    $this->actingAs($employee)
        ->get(route('activity.index'))
        ->assertForbidden();
});

it('zoekt op vertaalde actienaam', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    AuditLog::create([
        'user_id' => $admin->id,
        'tenant_id' => $tenant->id,
        'action' => 'work_shift.force_closed',
        'model_type' => \App\Models\WorkShift::class,
        'model_id' => 1,
        'payload' => ['reason' => 'Match op force'],
        'created_at' => now(),
    ]);
    AuditLog::create([
        'user_id' => $admin->id,
        'tenant_id' => $tenant->id,
        'action' => 'customer.created',
        'model_type' => \App\Models\Customer::class,
        'model_id' => 2,
        'payload' => ['name' => 'Verborgen klant'],
        'created_at' => now(),
    ]);

    \Livewire\Livewire::actingAs($admin)
        ->test(\App\Livewire\Pages\Activity::class)
        ->set('search', __('audit.actions')['work_shift.force_closed'])
        ->assertSee('Match op force')
        ->assertDontSee('Verborgen klant');
});
