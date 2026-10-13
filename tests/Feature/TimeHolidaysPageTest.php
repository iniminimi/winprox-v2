<?php

declare(strict_types=1);

use App\Livewire\Time\HolidaysIndex;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('toont een bevestiging na het opslaan van de feestdagenregeling', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);

    Livewire::actingAs($admin)
        ->test(HolidaysIndex::class)
        ->call('save')
        ->assertSee(__('time.holidays.saved'));
});
