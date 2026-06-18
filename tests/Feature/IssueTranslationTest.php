<?php

use App\Actions\Communication\ExportPendingIssueTranslationsAction;
use App\Actions\Communication\ImportIssueTranslationsAction;
use App\Actions\Communication\TranslateIssueAction;
use App\Actions\Issues\ApproveIssueAction;
use App\Actions\Issues\CreateIssueAction;
use App\Enums\IssueTranslationStatus;
use App\Livewire\Issues\Show as IssueShow;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\IssueTranslation;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Tenancy;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\FakeTranslationProvider;

afterEach(fn () => Tenancy::forget());

beforeEach(function () {
    app()->instance(TranslationProviderInterface::class, new FakeTranslationProvider);
});

it('seed pending vertaalrijen na goedkeuring', function () {
    $tenant = Tenant::factory()->create();
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $issue = app(CreateIssueAction::class)->handle([
        'description' => 'Kraan lekt',
        'source' => 'qr',
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'original_language' => 'nl',
    ], [$team->id]);

    expect(IssueTranslation::query()->where('issue_id', $issue->id)->count())->toBe(0);

    app(ApproveIssueAction::class)->handle($issue, $user);

    $rows = IssueTranslation::query()->where('issue_id', $issue->id)->get();
    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('locale')->sort()->values()->all())->toBe(['de', 'en', 'fr'])
        ->and($rows->every(fn ($row) => $row->status === IssueTranslationStatus::Pending))->toBeTrue();
});

it('vertaalt een melding via de provider en slaat op', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Broken window',
        'original_language' => 'en',
        'approved_at' => now(),
        'approved_by' => $user->id,
    ]);

    $row = app(TranslateIssueAction::class)->handle($issue, 'nl', $user->id);

    expect($row->status)->toBe(IssueTranslationStatus::Completed)
        ->and($row->text)->toBe('[nl] Broken window')
        ->and($issue->fresh()->localizedDescription('nl'))->toBe('[nl] Broken window');
});

it('weigert vertaling voor ongekeurde melding', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Nog niet goedgekeurd',
        'original_language' => 'nl',
        'approved_at' => null,
    ]);

    expect(fn () => app(TranslateIssueAction::class)->handle($issue, 'en'))
        ->toThrow(ValidationException::class);
});

it('exporteert en importeert pending vertalingen', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Lamp kapot',
        'original_language' => 'nl',
        'approved_at' => null,
    ]);

    app(ApproveIssueAction::class)->handle($issue, $user);

    $export = app(ExportPendingIssueTranslationsAction::class)->handle();
    expect($export['items'])->toHaveCount(3);

    $imported = app(ImportIssueTranslationsAction::class)->handle([
        [
            'issue_id' => $issue->id,
            'locale' => 'en',
            'text' => 'Broken lamp',
        ],
    ]);

    expect($imported)->toBe(1)
        ->and($issue->fresh()->localizedDescription('en'))->toBe('Broken lamp');
});

it('toont taalkiezer op meldingdetail met placeholder bij ontbrekende vertaling', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Broken window',
        'original_language' => 'en',
        'approved_at' => now(),
        'approved_by' => $user->id,
    ]);

    IssueTranslation::query()->create([
        'issue_id' => $issue->id,
        'locale' => 'nl',
        'status' => IssueTranslationStatus::Pending,
        'text' => null,
    ]);

    Livewire::actingAs($user)
        ->test(IssueShow::class, ['issue' => $issue])
        ->assertSeeHtml('id="descriptionLocale"')
        ->set('descriptionLocale', 'nl')
        ->assertSee(__('issues.show.description_not_translated', [], 'nl'));
});

it('toont vertaalde omschrijving op meldingdetail bij gekozen taal', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Broken window',
        'original_language' => 'en',
        'approved_at' => now(),
        'approved_by' => $user->id,
    ]);

    IssueTranslation::query()->create([
        'issue_id' => $issue->id,
        'locale' => 'nl',
        'status' => IssueTranslationStatus::Completed,
        'text' => 'Kapot raam',
    ]);

    Livewire::actingAs($user)
        ->test(IssueShow::class, ['issue' => $issue])
        ->set('descriptionLocale', 'nl')
        ->assertSee('Kapot raam')
        ->assertDontSee(__('issues.show.description_not_translated', [], 'nl'));
});

it('toont geen taalkiezer op ongekeurde meldingdetail', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Nog niet goedgekeurd',
        'original_language' => 'nl',
        'approved_at' => null,
    ]);

    Livewire::actingAs($user)
        ->test(IssueShow::class, ['issue' => $issue])
        ->assertSee('Nog niet goedgekeurd')
        ->assertDontSeeHtml('id="descriptionLocale"');
});
