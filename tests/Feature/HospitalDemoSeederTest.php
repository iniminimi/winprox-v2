<?php

use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use Database\Seeders\HospitalDemoSeeder;
use Illuminate\Support\Facades\Hash;

it('remplit un tenant avec des données de démo hospitalière en français', function () {
    $tenant = Tenant::factory()->create(['name' => 'Tenant démo']);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'password' => Hash::make('password'),
    ]);

    app(HospitalDemoSeeder::class)->run((int) $tenant->id, (int) $admin->id);

    expect(InternalTeam::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(4)
        ->and(Worker::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(10)
        ->and(Location::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(5)
        ->and(Unit::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(20)
        ->and(Issue::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(20)
        ->and(Task::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(20)
        ->and($tenant->fresh()->name)->toBe('Hôpital Saint-Raphaël')
        ->and(
            Issue::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereNull('approved_at')
                ->count(),
        )->toBe(4);
});
