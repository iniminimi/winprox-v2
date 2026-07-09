<?php

declare(strict_types=1);

use App\Actions\Esg\RecordEsgMeasurementAction;
use App\Actions\Esg\RecordEsgMeasurementCorrectionAction;
use App\Data\Esg\RecordEsgMeasurementData;
use App\Http\Requests\Esg\RecordEsgMeasurementCorrectionRequest;
use App\Livewire\Esg\MeasurementsIndex;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * @return array{
 *     tenant: Tenant,
 *     location: Location,
 *     unit: Unit,
 *     indicator: EsgIndicator,
 *     issue: Issue,
 *     task: Task,
 *     original: EsgMeasurement
 * }
 */
function esgCorrectionFixture(): array
{
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    $indicator = EsgIndicator::factory()->numeric('m3')->create(['tenant_id' => $tenant->id, 'name' => 'Gas']);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'esg_indicator_id' => $indicator->id,
        'is_recurring' => true,
    ]);
    $task = Task::factory()->create(['tenant_id' => $tenant->id, 'issue_id' => $issue->id]);

    $original = app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $task->id,
            esgIndicatorId: $indicator->id,
            recordedAt: now()->subHour()->toImmutable(),
            valueNumeric: 10.0,
        ),
        $tenant->id,
    );

    return compact('tenant', 'location', 'unit', 'indicator', 'issue', 'task', 'original');
}

it('registreert een correctie via de correction action', function () {
    $fixture = esgCorrectionFixture();
    $recordedAt = now()->toImmutable();

    $validated = RecordEsgMeasurementCorrectionRequest::livewireToValidated($fixture['original'], [
        'correctionValueNumeric' => '12.5',
        'correctionRecordedAt' => $recordedAt->format('Y-m-d\TH:i'),
    ]);
    $data = RecordEsgMeasurementCorrectionRequest::toData($fixture['original'], $validated);

    $correction = app(RecordEsgMeasurementCorrectionAction::class)->handle(
        $fixture['original'],
        $data,
        $fixture['tenant']->id,
    );

    expect(EsgMeasurement::count())->toBe(2)
        ->and($correction->corrects_measurement_id)->toBe($fixture['original']->id)
        ->and((float) $correction->value_numeric)->toBe(12.5)
        ->and((float) $fixture['original']->fresh()->value_numeric)->toBe(10.0);
});

it('weigert correctie op een eerdere correctie', function () {
    $fixture = esgCorrectionFixture();
    $validated = RecordEsgMeasurementCorrectionRequest::livewireToValidated($fixture['original'], [
        'correctionValueNumeric' => '11',
        'correctionRecordedAt' => now()->format('Y-m-d\TH:i'),
    ]);
    $data = RecordEsgMeasurementCorrectionRequest::toData($fixture['original'], $validated);

    $firstCorrection = app(RecordEsgMeasurementCorrectionAction::class)->handle(
        $fixture['original'],
        $data,
        $fixture['tenant']->id,
    );

    expect(fn () => RecordEsgMeasurementCorrectionRequest::assertOriginalCanBeCorrected($firstCorrection))
        ->toThrow(ValidationException::class);
});

it('laat een admin een correctie registreren via livewire', function () {
    $fixture = esgCorrectionFixture();
    $user = User::factory()->admin()->create(['tenant_id' => $fixture['tenant']->id]);

    Livewire::actingAs($user)
        ->test(MeasurementsIndex::class)
        ->call('openCorrectionModal', $fixture['original']->id)
        ->assertSet('showCorrectionModal', true)
        ->set('correctionValueNumeric', '15')
        ->set('correctionRecordedAt', now()->format('Y-m-d\TH:i'))
        ->call('saveCorrection')
        ->assertSet('showCorrectionModal', false);

    $correction = EsgMeasurement::query()
        ->where('corrects_measurement_id', $fixture['original']->id)
        ->first();

    expect($correction)->not->toBeNull()
        ->and((float) $correction->value_numeric)->toBe(15.0);
});

it('toont correctie-keten in het metingen-overzicht', function () {
    $fixture = esgCorrectionFixture();
    $user = User::factory()->admin()->create(['tenant_id' => $fixture['tenant']->id]);
    $recordedAt = now()->format('Y-m-d\TH:i');

    Livewire::actingAs($user)
        ->test(MeasurementsIndex::class)
        ->call('openCorrectionModal', $fixture['original']->id)
        ->set('correctionValueNumeric', '20')
        ->set('correctionRecordedAt', $recordedAt)
        ->call('saveCorrection');

    Livewire::actingAs($user)
        ->test(MeasurementsIndex::class)
        ->assertSee(__('esg.measurements.correction'))
        ->assertSee(__('esg.measurements.corrects_original', ['value' => '10 m3']))
        ->assertSee('20 m3');
});

it('verbergt corrigeren-knop op correctierijen', function () {
    $fixture = esgCorrectionFixture();
    $user = User::factory()->admin()->create(['tenant_id' => $fixture['tenant']->id]);

    $validated = RecordEsgMeasurementCorrectionRequest::livewireToValidated($fixture['original'], [
        'correctionValueNumeric' => '11',
        'correctionRecordedAt' => now()->format('Y-m-d\TH:i'),
    ]);
    $data = RecordEsgMeasurementCorrectionRequest::toData($fixture['original'], $validated);
    app(RecordEsgMeasurementCorrectionAction::class)->handle(
        $fixture['original'],
        $data,
        $fixture['tenant']->id,
        actorUserId: $user->id,
    );

    $correction = EsgMeasurement::query()
        ->where('corrects_measurement_id', $fixture['original']->id)
        ->firstOrFail();

    Livewire::actingAs($user)
        ->test(MeasurementsIndex::class)
        ->assertSeeHtml('wire:click="openCorrectionModal('.$fixture['original']->id.')"')
        ->assertDontSeeHtml('wire:click="openCorrectionModal('.$correction->id.')"');
});

it('weigert correct policy op correctierijen', function () {
    $fixture = esgCorrectionFixture();
    $user = User::factory()->admin()->create(['tenant_id' => $fixture['tenant']->id]);

    $validated = RecordEsgMeasurementCorrectionRequest::livewireToValidated($fixture['original'], [
        'correctionValueNumeric' => '11',
        'correctionRecordedAt' => now()->format('Y-m-d\TH:i'),
    ]);
    $data = RecordEsgMeasurementCorrectionRequest::toData($fixture['original'], $validated);
    $correction = app(RecordEsgMeasurementCorrectionAction::class)->handle(
        $fixture['original'],
        $data,
        $fixture['tenant']->id,
        actorUserId: $user->id,
    );

    expect($user->can('correct', $fixture['original']))->toBeTrue()
        ->and($user->can('correct', $correction))->toBeFalse();
});
