<?php

declare(strict_types=1);

use App\Actions\Esg\RecordEsgMeasurementAction;
use App\Data\Esg\RecordEsgMeasurementData;
use App\Events\Esg\EsgMeasurementRecorded;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

afterEach(fn () => Tenancy::forget());

/**
 * @return array{
 *     tenant: Tenant,
 *     user: User,
 *     location: Location,
 *     unit: Unit,
 *     indicator: EsgIndicator,
 *     issue: Issue,
 *     task: Task
 * }
 */
function esgApiMeasurementFixture(): array
{
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    $indicator = EsgIndicator::factory()->numeric('kWh')->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'esg_indicator_id' => $indicator->id,
        'is_recurring' => true,
        'approved_at' => now(),
    ]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
    ]);

    return compact('tenant', 'user', 'location', 'unit', 'indicator', 'issue', 'task');
}

it('registreert een ESG-meting via de API', function () {
    $fixture = esgApiMeasurementFixture();
    $token = $fixture['user']->createToken('test', ['esg:create'])->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/esg/measurements', [
        'task_id' => $fixture['task']->id,
        'esg_indicator_id' => $fixture['indicator']->id,
        'recorded_at' => '2026-07-08T10:15:00+02:00',
        'value_numeric' => 456.78,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.task_id', $fixture['task']->id)
        ->assertJsonPath('data.esg_indicator_id', $fixture['indicator']->id)
        ->assertJsonPath('data.value_numeric', 456.78);

    Tenancy::actAs($fixture['tenant']->id);
    expect(EsgMeasurement::count())->toBe(1);
});

it('weigert ESG API zonder esg:create ability', function () {
    $fixture = esgApiMeasurementFixture();
    $token = $fixture['user']->createToken('test', ['tasks:read'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/esg/measurements', [
        'task_id' => $fixture['task']->id,
        'esg_indicator_id' => $fixture['indicator']->id,
        'recorded_at' => now()->toIso8601String(),
        'value_numeric' => 1,
    ])->assertForbidden();
});

it('weigert ESG API zonder esg-module', function () {
    $fixture = esgApiMeasurementFixture();
    $fixture['tenant']->update(['has_esg_module' => false]);
    $token = $fixture['user']->createToken('test', ['esg:create'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/esg/measurements', [
        'task_id' => $fixture['task']->id,
        'esg_indicator_id' => $fixture['indicator']->id,
        'recorded_at' => now()->toIso8601String(),
        'value_numeric' => 1,
    ])->assertForbidden();
});

it('stuurt esg.measurement.recorded webhook met geldige HMAC', function () {
    Http::fake(['https://hooks.test/*' => Http::response('ok', 200)]);

    $fixture = esgApiMeasurementFixture();
    Tenancy::actAs($fixture['tenant']->id);

    $endpoint = WebhookEndpoint::factory()->create([
        'tenant_id' => $fixture['tenant']->id,
        'url' => 'https://hooks.test/winprox',
        'events' => ['esg.measurement.recorded'],
    ]);

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 12.5,
        ),
        $fixture['tenant']->id,
        actorUserId: $fixture['user']->id,
    );

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($endpoint) {
        if ($request->url() !== $endpoint->url) {
            return false;
        }

        $timestamp = $request->header('X-WinProx-Timestamp')[0] ?? '';
        $signature = $request->header('X-WinProx-Signature')[0] ?? '';
        $body = $request->body();
        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $endpoint->secret);

        return hash_equals($expected, $signature)
            && ($request->header('X-WinProx-Event')[0] ?? '') === 'esg.measurement.recorded';
    });
});

it('bevat esg.measurement.recorded in AVAILABLE_EVENTS', function () {
    expect(\App\Models\WebhookEndpoint::AVAILABLE_EVENTS)->toContain('esg.measurement.recorded');
});

it('dispatcht EsgMeasurementRecorded na registratie', function () {
    Event::fake([EsgMeasurementRecorded::class]);

    $fixture = esgApiMeasurementFixture();
    Tenancy::actAs($fixture['tenant']->id);

    app(RecordEsgMeasurementAction::class)->handle(
        new RecordEsgMeasurementData(
            taskId: $fixture['task']->id,
            esgIndicatorId: $fixture['indicator']->id,
            recordedAt: now()->toImmutable(),
            valueNumeric: 3.14,
        ),
        $fixture['tenant']->id,
    );

    Event::assertDispatched(EsgMeasurementRecorded::class);
});
