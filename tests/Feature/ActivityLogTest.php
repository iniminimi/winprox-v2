<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Tenancy;

it('toont een leesbare zin met actor, onderwerp en reden aan een admin', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'name' => 'Jan Peeters',
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Iris',
        'last_name' => 'Vandamme',
    ]);
    $shift = WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
    ]);

    AuditLog::create([
        'user_id' => $admin->id,
        'tenant_id' => $tenant->id,
        'action' => 'work_shift.force_closed',
        'model_type' => WorkShift::class,
        'model_id' => $shift->id,
        'payload' => ['reason' => 'Werknemer vergeten uit te klokken'],
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('activity.index'))
        ->assertOk()
        ->assertSee('Jan Peeters sloot de dienst van Iris Vandamme geforceerd af', false)
        ->assertSee('Werknemer vergeten uit te klokken', false);
});

it('toont een neutrale fallback bij een verwijderd onderwerp', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    AuditLog::create([
        'user_id' => $admin->id,
        'tenant_id' => $tenant->id,
        'action' => 'worker.deleted',
        'model_type' => Worker::class,
        'model_id' => 999,
        'payload' => [],
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('activity.index'))
        ->assertOk()
        ->assertSee('Verwijderde uitvoerder (#999)', false);
});

it('verbergt operationele hartslag maar toont automatische afsluitingen', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Lina',
        'last_name' => 'Uren',
    ]);
    $shift = WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
    ]);

    // Gewone inklok + schermprik: ruis, niet tonen.
    AuditLog::create([
        'user_id' => null, 'tenant_id' => $tenant->id,
        'action' => 'time.shift.started', 'model_type' => WorkShift::class, 'model_id' => $shift->id,
        'payload' => ['worker_id' => $worker->id], 'created_at' => now()->subMinutes(5),
    ]);
    AuditLog::create([
        'user_id' => null, 'tenant_id' => $tenant->id,
        'action' => 'worker.clock_display_punched', 'model_type' => Worker::class, 'model_id' => $worker->id,
        'payload' => [], 'created_at' => now()->subMinutes(4),
    ]);
    // Normale uitklok (status closed): gedekt door Uren, niet tonen.
    AuditLog::create([
        'user_id' => null, 'tenant_id' => $tenant->id,
        'action' => 'time.shift.ended', 'model_type' => WorkShift::class, 'model_id' => $shift->id,
        'payload' => ['status' => 'closed', 'worker_id' => $worker->id], 'created_at' => now()->subMinutes(3),
    ]);
    // Automatische systeem-afsluiting (force_closed, geen actor): wél tonen.
    AuditLog::create([
        'user_id' => null, 'tenant_id' => $tenant->id,
        'action' => 'time.shift.ended', 'model_type' => WorkShift::class, 'model_id' => $shift->id,
        'payload' => ['status' => 'force_closed', 'worker_id' => $worker->id], 'created_at' => now()->subMinutes(2),
    ]);

    $response = $this->actingAs($admin)->get(route('activity.index'));
    $response->assertOk()
        ->assertSee('De dienst van Lina Uren werd automatisch afgesloten', false);

    // Alleen de automatische afsluiting blijft over in het verslag.
    $result = app(\App\Actions\Audit\ListTenantAuditLogsAction::class)->handle($tenant->id);
    expect($result['rows']->getCollection()->pluck('action')->all())
        ->toBe(['time.shift.ended']);
});

it('filtert op categorie', function () {
    app()->setLocale('nl');
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $boundWorker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Gekoppelde',
        'last_name' => 'Medewerker',
    ]);

    AuditLog::create([
        'user_id' => $admin->id, 'tenant_id' => $tenant->id,
        'action' => 'worker.deleted', 'model_type' => Worker::class, 'model_id' => 5,
        'payload' => [], 'created_at' => now(),
    ]);
    AuditLog::create([
        'user_id' => $admin->id, 'tenant_id' => $tenant->id,
        'action' => 'work_shift.corrected', 'model_type' => WorkShift::class, 'model_id' => 9,
        'payload' => ['reason' => 'Verkeerd ingeklokt'], 'created_at' => now(),
    ]);
    AuditLog::create([
        'user_id' => $admin->id, 'tenant_id' => $tenant->id,
        'action' => 'worker.clock_device_bound', 'model_type' => Worker::class, 'model_id' => $boundWorker->id,
        'payload' => [], 'created_at' => now(),
    ]);

    // Uitvoerders: worker.deleted wel, dienst-correctie niet, device-bind (security) niet.
    \Livewire\Livewire::actingAs($admin)
        ->test(\App\Livewire\Pages\Activity::class)
        ->set('category', 'workers')
        ->assertSee('verwijderde uitvoerder')
        ->assertSee('Verwijderde uitvoerder (#5)', false)
        ->assertDontSee('corrigeerde de dienst')
        ->assertDontSee('koppelde een toestel');

    // Beveiliging: enkel de device-bind.
    \Livewire\Livewire::actingAs($admin)
        ->test(\App\Livewire\Pages\Activity::class)
        ->set('category', 'security')
        ->assertSee('koppelde een toestel')
        ->assertDontSee('Verwijderde uitvoerder');
});

it('zoekt op opgeloste onderwerp-naam', function () {
    app()->setLocale('nl');
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Zoekbare',
        'last_name' => 'Naam',
    ]);
    $shift = WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
    ]);

    AuditLog::create([
        'user_id' => $admin->id, 'tenant_id' => $tenant->id,
        'action' => 'work_shift.force_closed', 'model_type' => WorkShift::class, 'model_id' => $shift->id,
        'payload' => ['worker_id' => $worker->id, 'reason' => 'Match op naam'],
        'created_at' => now(),
    ]);
    AuditLog::create([
        'user_id' => $admin->id, 'tenant_id' => $tenant->id,
        'action' => 'customer.created', 'model_type' => \App\Models\Customer::class, 'model_id' => 2,
        'payload' => ['name' => 'Andere rij'], 'created_at' => now(),
    ]);

    \Livewire\Livewire::actingAs($admin)
        ->test(\App\Livewire\Pages\Activity::class)
        ->set('search', 'Zoekbare')
        ->assertSee('Match op naam')
        ->assertDontSee('Andere rij');
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
        'model_type' => WorkShift::class,
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
    app()->setLocale('nl');
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    AuditLog::create([
        'user_id' => $admin->id, 'tenant_id' => $tenant->id,
        'action' => 'work_shift.force_closed', 'model_type' => WorkShift::class, 'model_id' => 1,
        'payload' => ['reason' => 'Match op force'], 'created_at' => now(),
    ]);
    AuditLog::create([
        'user_id' => $admin->id, 'tenant_id' => $tenant->id,
        'action' => 'customer.created', 'model_type' => \App\Models\Customer::class, 'model_id' => 2,
        'payload' => ['name' => 'Verborgen klant'], 'created_at' => now(),
    ]);

    \Livewire\Livewire::actingAs($admin)
        ->test(\App\Livewire\Pages\Activity::class)
        ->set('search', __('audit.actions')['work_shift.force_closed'])
        ->assertSee('Match op force')
        ->assertDontSee('Verborgen klant');
});
