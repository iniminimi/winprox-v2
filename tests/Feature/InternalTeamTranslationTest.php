<?php

use App\Actions\Communication\EnsureInternalTeamTranslationSlotsAction;
use App\Actions\Communication\ExportPendingInternalTeamTranslationsAction;
use App\Actions\Communication\ImportInternalTeamTranslationsAction;
use App\Actions\Communication\TranslateInternalTeamAction;
use App\Actions\Team\CreateTeamAction;
use App\Enums\InternalTeamTranslationStatus;
use App\Models\InternalTeam;
use App\Models\InternalTeamTranslation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Tenancy;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakeTranslationProvider;

afterEach(fn () => Tenancy::forget());

beforeEach(function () {
    app()->instance(TranslationProviderInterface::class, new FakeTranslationProvider);
});

it('seed pending vertaalrijen na aanmaken actief team', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'nl']);
    Tenancy::actAs($tenant->id);

    $team = app(CreateTeamAction::class)->handle([
        'name' => 'Technische dienst',
        'original_language' => 'nl',
        'is_active' => true,
    ], $tenant->id, $user->id);

    $rows = InternalTeamTranslation::query()->where('internal_team_id', $team->id)->get();
    $expectedLocales = collect(expectedTargetLocales('nl'))->sort()->values()->all();

    expect($rows)->toHaveCount(count($expectedLocales))
        ->and($rows->pluck('locale')->sort()->values()->all())->toBe($expectedLocales)
        ->and($rows->every(fn ($row) => $row->status === InternalTeamTranslationStatus::Pending))->toBeTrue();
});

it('exporteert en importeert pending teamvertalingen', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Technische dienst',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureInternalTeamTranslationSlotsAction::class)->handle($team);

    $exportItems = app(ExportPendingInternalTeamTranslationsAction::class)->handle();

    expect($exportItems)->toHaveCount(count(expectedTargetLocales('nl')))
        ->and($exportItems[0])->toHaveKeys(['internal_team_id', 'source_name', 'locale']);

    $imported = app(ImportInternalTeamTranslationsAction::class)->handle([
        [
            'internal_team_id' => $team->id,
            'locale' => 'en',
            'name' => 'Technical service',
        ],
    ]);

    expect($imported)->toBe(1)
        ->and($team->fresh()->localizedName('en'))->toBe('Technical service');
});

it('vertaalt team via de provider', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Technische dienst',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureInternalTeamTranslationSlotsAction::class)->handle($team);
    app(TranslateInternalTeamAction::class)->handle($team, 'en');

    $row = InternalTeamTranslation::query()->where('internal_team_id', $team->id)->where('locale', 'en')->first();

    expect($row->status)->toBe(InternalTeamTranslationStatus::Completed)
        ->and($row->name)->toBe('[en] Technische dienst')
        ->and($team->fresh()->localizedName('en'))->toBe('[en] Technische dienst');
});

it('weigert vertalen van inactief team', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Technische dienst',
        'original_language' => 'nl',
        'is_active' => false,
    ]);

    expect(fn () => app(TranslateInternalTeamAction::class)->handle($team, 'en'))
        ->toThrow(ValidationException::class);
});

it('weigert import van te lange teamnaam', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Technische dienst',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureInternalTeamTranslationSlotsAction::class)->handle($team);

    expect(fn () => app(ImportInternalTeamTranslationsAction::class)->handle([
        [
            'internal_team_id' => $team->id,
            'locale' => 'en',
            'name' => str_repeat('x', 256),
        ],
    ]))->toThrow(ValidationException::class);
});
