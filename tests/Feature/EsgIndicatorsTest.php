<?php

use App\Actions\Esg\RecordEsgMeasurementAction;
use App\Data\Esg\RecordEsgMeasurementData;
use App\Enums\EsgIndicatorCategory;
use App\Enums\EsgIndicatorType;
use App\Livewire\Esg\IndicatorsIndex;
use App\Models\EsgIndicator;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Livewire\Livewire;

it('laat een admin een indicator met categorie beheren', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(IndicatorsIndex::class)
        ->call('openCreateModal')
        ->set('name', 'Waterverbruik m3')
        ->set('type', 'numeric')
        ->set('category', EsgIndicatorCategory::Water->value)
        ->set('unitOfMeasure', 'm3')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee(__('esg.categories.water'));

    $indicator = EsgIndicator::query()->where('tenant_id', $tenant->id)->first();

    expect($indicator)->not->toBeNull()
        ->and($indicator->category)->toBe(EsgIndicatorCategory::Water);
});

it('toont elke indicator eenmaal in de lijst', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    EsgIndicator::factory()->choice(['Restafval', 'PMD'])->create([
        'tenant_id' => $tenant->id,
        'name' => 'Afvalstroom',
    ]);
    EsgIndicator::factory()->numeric('kg')->create([
        'tenant_id' => $tenant->id,
        'name' => 'CO₂ kg',
    ]);
    EsgIndicator::factory()->numeric('kWh')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Elektriciteit kWh',
    ]);

    $html = Livewire::actingAs($user)
        ->test(IndicatorsIndex::class)
        ->html();

    expect(substr_count($html, '<strong>Afvalstroom</strong>'))->toBe(1)
        ->and(substr_count($html, '<strong>CO₂ kg</strong>'))->toBe(1)
        ->and(substr_count($html, '<strong>Elektriciteit kWh</strong>'))->toBe(1);
});

it('weigert toegang tot indicatoren zonder esg-module', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => false]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('esg.indicators.index'))
        ->assertForbidden();
});

it('weigert indicatoren voor medewerkers zonder admin-rechten', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('esg.indicators.index'))
        ->assertForbidden();
});

it('laat een admin met esg-module indicatoren beheren', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(IndicatorsIndex::class)
        ->call('openCreateModal')
        ->set('name', 'Elektriciteit kWh')
        ->set('type', 'numeric')
        ->set('unitOfMeasure', 'kWh')
        ->set('thresholdMin', '0')
        ->set('thresholdMax', '99999')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Elektriciteit kWh');

    $indicator = EsgIndicator::query()->where('tenant_id', $tenant->id)->first();

    expect($indicator)->not->toBeNull()
        ->and($indicator->name)->toBe('Elektriciteit kWh')
        ->and($indicator->type->value)->toBe('numeric')
        ->and($indicator->unit_of_measure)->toBe('kWh')
        ->and($indicator->thresholds)->toMatchArray(['min' => 0, 'max' => 99999]);
});

it('deactiveert een indicator zonder hard delete', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $indicator = EsgIndicator::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Gas m3',
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(IndicatorsIndex::class)
        ->call('toggleActive', $indicator->id)
        ->assertSee(__('esg.status.inactive'));

    expect($indicator->fresh()->is_active)->toBeFalse()
        ->and(EsgIndicator::query()->whereKey($indicator->id)->exists())->toBeTrue();
});

it('laat een admin een keuzelijst-indicator met opties beheren', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(IndicatorsIndex::class)
        ->call('openCreateModal')
        ->set('name', 'Afvalcategorie')
        ->set('type', 'choice')
        ->set('choiceOptions', ['Restafval', 'PMD', 'Papier'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Afvalcategorie');

    $indicator = EsgIndicator::query()->where('tenant_id', $tenant->id)->first();

    expect($indicator)->not->toBeNull()
        ->and($indicator->type)->toBe(EsgIndicatorType::Choice)
        ->and($indicator->options)->toBe(['Restafval', 'PMD', 'Papier']);
});

it('laat een admin een meervoudige-keuze-indicator met opties beheren', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(IndicatorsIndex::class)
        ->call('openCreateModal')
        ->set('name', 'Afvalstromen')
        ->set('type', 'multi_choice')
        ->set('choiceOptions', ['Restafval', 'PMD', 'Papier'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Afvalstromen');

    $indicator = EsgIndicator::query()->where('tenant_id', $tenant->id)->first();

    expect($indicator)->not->toBeNull()
        ->and($indicator->type)->toBe(EsgIndicatorType::MultiChoice)
        ->and($indicator->options)->toBe(['Restafval', 'PMD', 'Papier']);
});

it('weigert verwijderen van een keuze-optie die al in metingen voorkomt', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $indicator = EsgIndicator::factory()->choice(['Restafval', 'PMD', 'Papier'])->create([
        'tenant_id' => $tenant->id,
        'name' => 'Afvalcategorie',
    ]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'esg_indicator_id' => $indicator->id,
        'is_recurring' => true,
    ]);
    $task = Task::factory()->create(['tenant_id' => $tenant->id, 'issue_id' => $issue->id]);

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $task->id,
            esgIndicatorId: $indicator->id,
            recordedAt: now()->toImmutable(),
            valueString: 'PMD',
        ),
        $tenant->id,
    );

    Livewire::actingAs($user)
        ->test(IndicatorsIndex::class)
        ->call('openEditModal', $indicator->id)
        ->set('choiceOptions', ['Restafval', 'Papier'])
        ->call('save')
        ->assertHasErrors(['choiceOptions']);
});

it('weigert verwijderen van een meervoudige-keuze-optie die al in metingen voorkomt', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $indicator = EsgIndicator::factory()->multiChoice(['Restafval', 'PMD', 'Papier'])->create([
        'tenant_id' => $tenant->id,
        'name' => 'Afvalstromen',
    ]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'esg_indicator_id' => $indicator->id,
        'is_recurring' => true,
    ]);
    $task = Task::factory()->create(['tenant_id' => $tenant->id, 'issue_id' => $issue->id]);

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $task->id,
            esgIndicatorId: $indicator->id,
            recordedAt: now()->toImmutable(),
            valueJson: ['PMD', 'Papier'],
        ),
        $tenant->id,
    );

    Livewire::actingAs($user)
        ->test(IndicatorsIndex::class)
        ->call('openEditModal', $indicator->id)
        ->set('choiceOptions', ['Restafval', 'Papier'])
        ->call('save')
        ->assertHasErrors(['choiceOptions']);
});

it('isoleert indicatoren per tenant', function () {
    $tenantA = Tenant::factory()->create(['has_esg_module' => true]);
    $tenantB = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenantA->id]);

    EsgIndicator::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Andere tenant']);

    Livewire::actingAs($user)
        ->test(IndicatorsIndex::class)
        ->assertDontSee('Andere tenant');
});

it('toont setup-stappen bij lege indicatorenlijst', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(IndicatorsIndex::class)
        ->assertSee(__('esg.setup.title'))
        ->assertSee(__('esg.setup.steps')[0], false);
});

it('toont esg-navigatie alleen wanneer module actief is', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true, 'trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSee(__('common.nav.esg'));
});

it('verbergt esg-navigatie zonder module', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => false, 'trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertDontSee(__('common.nav.esg'));
});
