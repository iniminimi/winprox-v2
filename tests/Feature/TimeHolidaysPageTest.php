<?php

declare(strict_types=1);

use App\Livewire\Time\HolidaysIndex;
use App\Models\Tenant;
use App\Models\TenantHolidayAgreement;
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

it('bewaart alleen afwijkingen van de afgeleide standaard en herstelt met standaard', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);

    $c = Livewire::actingAs($admin)->test(HolidaysIndex::class)->call('setYear', 2026);

    // Niets gekozen → opslaan schrijft geen rijen (alles volgt de standaard).
    $c->call('save');
    expect(TenantHolidayAgreement::query()->where('tenant_id', $tenant->id)->count())->toBe(0);

    // 15 aug 2026 (za): afgeleide standaard = vervangingsdag met voorstel
    // ma 17 aug. Kiest de beheerder 'vervangingsdag', dan vult het
    // datumveld het voorstel voor → opslaan legt de afspraak vast.
    $index = collect($c->get('agreements'))->search(fn ($row) => $row['date'] === '2026-08-15');
    expect($index)->not->toBeFalse();
    $c->assertSet("agreements.$index.standard_effect", 'replaced')
        ->assertSet("agreements.$index.standard_replacement", '2026-08-17')
        ->set("agreements.$index.effect", 'replaced')
        ->assertSet("agreements.$index.replacement_date", '2026-08-17')
        ->call('save');

    $row = TenantHolidayAgreement::query()
        ->where('tenant_id', $tenant->id)
        ->whereDate('holiday_date', '2026-08-15')
        ->first();
    expect($row)->not->toBeNull()
        ->and($row->replacement_date->toDateString())->toBe('2026-08-17');

    // 'Standaard' kiezen herstelt de afgeleide regeling en verwijdert de rij.
    $c->set("agreements.$index.effect", '')->call('save');
    expect(TenantHolidayAgreement::query()->where('tenant_id', $tenant->id)->count())->toBe(0);
});
