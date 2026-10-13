<?php

declare(strict_types=1);

use App\Actions\Time\ListRosterWeekAction;
use App\Enums\HolidayAgreementEffect;
use App\Models\Tenant;
use App\Models\TenantHolidayAgreement;
use App\Models\User;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

function dayFlagsAdmin(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    return [$tenant, $admin];
}

function dayFlagsFor(int $tenantId, string $start, ?User $actor, string $period = 'week'): array
{
    return app(ListRosterWeekAction::class)
        ->handle($tenantId, $start, null, $actor, $period)
        ->dayFlags;
}

it('markeert een feestdag op een werkdag via de afgeleide standaard', function () {
    [$tenant, $admin] = dayFlagsAdmin();

    // Woensdag 11 nov 2026 (Wapenstilstand) — geen afspraak → werkdag vervalt.
    $flags = dayFlagsFor((int) $tenant->id, '2026-11-09', $admin);

    expect($flags['2026-11-11']['kind'])->toBe('holiday')
        ->and($flags['2026-11-11']['label'])
        ->toContain(__('time.schedule.month_norm.holidays.armistice'))
        ->toContain(__('time.schedule.month_norm.effects.dropped'));
});

it('markeert de afgeleide vervangingsdag van een weekendfeestdag', function () {
    [$tenant, $admin] = dayFlagsAdmin();

    // Zondag 1 nov 2026 (Allerheiligen) — afgeleid voorstel: maandag 2 nov.
    $flags = dayFlagsFor((int) $tenant->id, '2026-11-02', $admin);

    expect($flags['2026-11-02']['kind'])->toBe('replacement')
        ->and($flags['2026-11-02']['label'])->toBe(__(
            'time.schedule.day_flag.replacement',
            [
                'name' => __('time.schedule.month_norm.holidays.all_saints'),
                'date' => '01/11/2026',
            ],
        ));
});

it('markeert een vastgelegde vervangingsdag van de tenant', function () {
    [$tenant, $admin] = dayFlagsAdmin();

    TenantHolidayAgreement::query()->create([
        'tenant_id' => $tenant->id,
        'holiday_date' => '2026-11-01',
        'effect' => HolidayAgreementEffect::Replaced,
        'replacement_date' => '2026-11-02',
    ]);

    $flags = dayFlagsFor((int) $tenant->id, '2026-11-02', $admin);

    expect($flags['2026-11-02']['kind'])->toBe('replacement')
        ->and($flags['2026-11-02']['label'])
        ->toContain(__('time.schedule.month_norm.holidays.all_saints'));
});

it('laat de vervangingsvlag weg bij de override geen effect', function () {
    [$tenant, $admin] = dayFlagsAdmin();

    TenantHolidayAgreement::query()->create([
        'tenant_id' => $tenant->id,
        'holiday_date' => '2026-11-01',
        'effect' => HolidayAgreementEffect::None,
        'replacement_date' => null,
    ]);

    $flags = dayFlagsFor((int) $tenant->id, '2026-11-02', $admin);

    expect($flags)->not->toHaveKey('2026-11-02');
});

it('houdt tenant-afspraken gescheiden in de dagvlaggen', function () {
    [$tenantA, $adminA] = dayFlagsAdmin();
    [$tenantB, $adminB] = dayFlagsAdmin();

    // Tenant B legt een eigen vervangingsdag vast voor 15 aug (za).
    TenantHolidayAgreement::query()->create([
        'tenant_id' => $tenantB->id,
        'holiday_date' => '2026-08-15',
        'effect' => HolidayAgreementEffect::Replaced,
        'replacement_date' => '2026-08-19',
    ]);

    $flagsA = dayFlagsFor((int) $tenantA->id, '2026-08-17', $adminA);
    $flagsB = dayFlagsFor((int) $tenantB->id, '2026-08-17', $adminB);

    // A volgt de afgeleide standaard (ma 17 aug); B zijn eigen datum (wo 19 aug).
    expect($flagsA['2026-08-17']['kind'])->toBe('replacement')
        ->and($flagsA)->not->toHaveKey('2026-08-19')
        ->and($flagsB)->not->toHaveKey('2026-08-17')
        ->and($flagsB['2026-08-19']['kind'])->toBe('replacement');
});

it('vult de dagvlaggen ook in het maandoverzicht', function () {
    [$tenant, $admin] = dayFlagsAdmin();

    $flags = dayFlagsFor((int) $tenant->id, '2026-11-15', $admin, 'month');

    expect($flags['2026-11-02']['kind'])->toBe('replacement')
        ->and($flags['2026-11-11']['kind'])->toBe('holiday');
});
