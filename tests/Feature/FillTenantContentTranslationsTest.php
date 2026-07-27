<?php

use App\Actions\Communication\FillTenantContentTranslationsAction;
use App\Enums\IssueTranslationStatus;
use App\Enums\UnitTranslationStatus;
use App\Models\Issue;
use App\Models\IssueTranslation;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitTranslation;
use App\Models\User;
use App\Services\Translation\TranslationProviderInterface;
use Tests\Support\FakeTranslationProvider;

beforeEach(function () {
    app()->instance(TranslationProviderInterface::class, new FakeTranslationProvider);
});

it('vult pending content-vertalingen voor één tenant inclusief units', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->admin()->for($tenant)->create();

    $unit = Unit::factory()->for($tenant)->create([
        'name' => 'Boiler A',
        'description' => 'Hot water unit',
        'original_language' => 'en',
        'is_active' => true,
    ]);

    expect(UnitTranslation::query()->where('unit_id', $unit->id)->count())->toBe(0);

    $result = app(FillTenantContentTranslationsAction::class)->handle((int) $tenant->id);

    expect($result['pending'])->toBeGreaterThan(0)
        ->and($result['imported'])->toBeGreaterThan(0)
        ->and(
            UnitTranslation::query()
                ->where('unit_id', $unit->id)
                ->where('status', UnitTranslationStatus::Completed)
                ->count()
        )->toBe(5);
});

it('vult ontbrekende issue-vertalingen voor goedgekeurde meldingen', function () {
    $tenant = Tenant::factory()->create();
    $issue = Issue::factory()->for($tenant)->create([
        'description' => 'Broken window',
        'original_language' => 'en',
        'approved_at' => now(),
    ]);

    $result = app(FillTenantContentTranslationsAction::class)->handle((int) $tenant->id);

    expect($result['imported'])->toBeGreaterThan(0)
        ->and(
            IssueTranslation::query()
                ->where('issue_id', $issue->id)
                ->where('status', IssueTranslationStatus::Completed)
                ->count()
        )->toBe(5);
});
