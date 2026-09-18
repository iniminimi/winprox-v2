<?php

use App\Actions\Time\ClockInAction;
use App\Actions\Time\ListWorkDestinationsForWorkerAction;
use App\Actions\Time\StartWorkVisitAction;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Enums\TaskStatus;
use App\Livewire\Public\TimePortal;
use App\Models\Issue;
use App\Models\IssueRoundStop;
use App\Models\Location;
use App\Models\PlannedShift;
use App\Models\PresenceSubmission;
use App\Models\Task;
use App\Models\Unit;
use App\Models\UnitGpsReport;
use App\Models\WorkVisit;
use App\Support\Tenancy;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function ensureTestEncryptionKey(): void
{
    if (filled((string) config('app.key'))) {
        return;
    }

    $raw = str_repeat('w', 32);
    config(['app.key' => 'base64:'.base64_encode($raw)]);
    app()->instance('encrypter', new Encrypter($raw, (string) config('app.cipher')));
}

function workDestTodayTask(array $ctx, Unit $unit, Location $location, array $taskAttrs = []): Task
{
    [$tenant, $worker] = $ctx;
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'approved_at' => now(),
    ]);

    return Task::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $worker->internal_team_id,
        'status' => TaskStatus::New,
        'scheduled_for' => now()->toDateString(),
    ], $taskAttrs));
}

it('toont twee bestemmingen voor twee taken op twee locaties vandaag', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    $locationB = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hotel De Brug',
        'street' => 'Kerkstraat',
        'house_number' => '12',
        'postal_code' => '8000',
        'city' => 'Brugge',
    ]);
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $locationB->id,
        'name' => 'Kamer 214',
        'latitude' => 51.21,
        'longitude' => 3.22,
        'is_active' => true,
    ]);
    $location->update(['name' => 'Campus Noord']);
    $unit->update(['name' => 'Gebouw A']);

    workDestTodayTask([$tenant, $worker], $unit, $location);
    workDestTodayTask([$tenant, $worker], $unitB, $locationB);

    $rows = app(ListWorkDestinationsForWorkerAction::class)->handle($tenant, $worker);

    expect($rows)->toHaveCount(2)
        ->and(collect($rows)->pluck('unitId')->all())->toEqualCanonicalizing([(int) $unit->id, (int) $unitB->id]);
});

it('groepeert Clock Point-bestemmingen per locatie met GPS-icoon', function () {
    ensureTestEncryptionKey();
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    $locationB = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hotel De Brug',
        'street' => 'Kerkstraat',
        'house_number' => '12',
        'postal_code' => '8000',
        'city' => 'Brugge',
    ]);
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $locationB->id,
        'name' => 'Kamer 214',
        'latitude' => 51.21,
        'longitude' => 3.22,
        'is_active' => true,
    ]);
    $worker->update([
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint->update(['qr_token' => 'today-group-'.$tenant->id]);
    $location->update(['name' => 'Campus Noord']);
    $unit->update(['name' => 'Gebouw A']);
    workDestTodayTask([$tenant, $worker], $unit, $location);
    workDestTodayTask([$tenant, $worker], $unitB, $locationB);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->call('clockIn')
        ->assertSee('Campus Noord, Teststraat 1, 1000 Brussel', false)
        ->assertSee('Hotel De Brug, Kerkstraat 12, 8000 Brugge', false)
        ->assertSee('Gebouw A', false)
        ->assertSee('Kamer 214', false)
        ->assertSeeHtml('wp-today-destination__pin')
        ->assertDontSeeHtml('btn btn--primary btn--block">'.__('time.portal.today.navigate'));
});

it('ontdubbelt twee taken op dezelfde unit tot één bestemming', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    workDestTodayTask([$tenant, $worker], $unit, $location, ['scheduled_for' => now()->toDateString()]);
    workDestTodayTask([$tenant, $worker], $unit, $location, [
        'scheduled_for' => null,
        'due_at' => now(),
    ]);

    $rows = app(ListWorkDestinationsForWorkerAction::class)->handle($tenant, $worker);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->unitId)->toBe((int) $unit->id);
});

it('neemt open taken zonder datum niet op in Vandaag', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    workDestTodayTask([$tenant, $worker], $unit, $location, [
        'scheduled_for' => null,
        'due_at' => null,
    ]);

    expect(app(ListWorkDestinationsForWorkerAction::class)->handle($tenant, $worker))->toBe([]);
});

it('klapt inspectiestops uit als bestemmingen', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Stop B',
        'latitude' => 51.06,
        'longitude' => 3.74,
        'is_active' => true,
    ]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => null,
        'unit_id' => null,
        'approved_at' => now(),
        'is_recurring' => true,
    ]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unit->id, 'sort_order' => 0]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unitB->id, 'sort_order' => 1]);
    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $worker->internal_team_id,
        'status' => TaskStatus::InProgress,
        'due_at' => now(),
    ]);

    $rows = app(ListWorkDestinationsForWorkerAction::class)->handle($tenant, $worker);

    expect($rows)->toHaveCount(2)
        ->and(collect($rows)->pluck('unitId')->all())->toEqualCanonicalizing([(int) $unit->id, (int) $unitB->id]);
});

it('toont een ronde zelf niet als navigeerbare bestemming', function () {
    [$tenant, $worker, $clockPoint, $location] = gpsVisitContext();
    $blank = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Zonder adres',
        'address' => '',
        'street' => '',
        'house_number' => '',
        'postal_code' => '',
        'city' => '',
        'is_active' => true,
    ]);
    $stopA = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $blank->id,
        'latitude' => null,
        'longitude' => null,
        'is_active' => true,
        'name' => 'Stop zonder pin',
    ]);
    $stopB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $blank->id,
        'latitude' => null,
        'longitude' => null,
        'is_active' => true,
        'name' => 'Andere stop',
    ]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => null,
        'unit_id' => null,
        'approved_at' => now(),
        'is_recurring' => true,
        'description' => 'Ronde zonder bestemming',
    ]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $stopA->id, 'sort_order' => 0]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $stopB->id, 'sort_order' => 1]);
    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $worker->internal_team_id,
        'status' => TaskStatus::New,
        'scheduled_for' => now()->toDateString(),
    ]);

    $rows = app(ListWorkDestinationsForWorkerAction::class)->handle($tenant, $worker);

    expect($rows)->toBe([])
        ->and(collect($rows)->pluck('locationName')->all())->not->toContain('Ronde zonder bestemming');
});

it('gebruikt de werkbezoek-pin voor de Maps-URL', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    $location->update([
        'street' => 'Kerkstraat',
        'house_number' => '12',
        'postal_code' => '8000',
        'city' => 'Brugge',
    ]);
    workDestTodayTask([$tenant, $worker], $unit, $location);

    $row = app(ListWorkDestinationsForWorkerAction::class)->handle($tenant, $worker)[0];

    expect($row->canStartVisit)->toBeTrue()
        ->and($row->mapsUrl)->toContain('51.05')
        ->and($row->mapsUrl)->toContain('3.73')
        ->and($row->mapsUrl)->not->toContain('Kerkstraat');
});

it('gebruikt het locatieadres zonder werkbezoek-pin', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    $unit->update(['latitude' => null, 'longitude' => null]);
    $location->update([
        'street' => 'Kerkstraat',
        'house_number' => '12',
        'postal_code' => '8000',
        'city' => 'Brugge',
        'address' => '',
    ]);
    workDestTodayTask([$tenant, $worker], $unit, $location);

    $row = app(ListWorkDestinationsForWorkerAction::class)->handle($tenant, $worker)[0];

    expect($row->canStartVisit)->toBeFalse()
        ->and($row->locationCanStart)->toBeFalse()
        ->and($row->mapsUrl)->toContain(rawurlencode('Kerkstraat 12, 8000 Brugge'))
        ->and($row->pinLatitude)->toBeNull();
});

it('vouwt units zonder pin onder de locatie-pin', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    $unit->update(['latitude' => null, 'longitude' => null, 'name' => 'Kamer 12']);
    $location->update([
        'name' => 'Ziekenhuis Noord',
        'latitude' => 51.05,
        'longitude' => 3.73,
    ]);
    workDestTodayTask([$tenant, $worker], $unit, $location);

    $row = app(ListWorkDestinationsForWorkerAction::class)->handle($tenant, $worker)[0];

    expect($row->canStartVisit)->toBeFalse()
        ->and($row->locationCanStart)->toBeTrue()
        ->and($row->unitName)->toBe('Kamer 12')
        ->and($row->locationMapsUrl)->toContain('51.05')
        ->and($row->mapsUrl)->toContain('51.05');
});

it('gebruikt geen oud GPS-rapport voor navigatie', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    $unit->update(['latitude' => null, 'longitude' => null]);
    $location->update([
        'street' => 'Kerkstraat',
        'house_number' => '12',
        'postal_code' => '8000',
        'city' => 'Brugge',
        'address' => '',
    ]);
    UnitGpsReport::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'latitude' => 50.00,
        'longitude' => 4.00,
    ]);
    workDestTodayTask([$tenant, $worker], $unit, $location);

    $row = app(ListWorkDestinationsForWorkerAction::class)->handle($tenant, $worker)[0];

    expect($row->mapsUrl)->not->toContain('50')
        ->and($row->mapsUrl)->not->toContain('4.00')
        ->and($row->mapsUrl)->toContain(rawurlencode('Kerkstraat 12, 8000 Brugge'));
});

it('maakt geen WorkVisit of CIAO bij het ophalen van bestemmingen', function () {
    Queue::fake();
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    workDestTodayTask([$tenant, $worker], $unit, $location);

    app(ListWorkDestinationsForWorkerAction::class)->handle($tenant, $worker);

    expect(WorkVisit::query()->count())->toBe(0)
        ->and(PresenceSubmission::query()->count())->toBe(0);
});

it('houdt Start werk achter de bestaande GPS-straal', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    workDestTodayTask([$tenant, $worker], $unit, $location);
    app(ClockInAction::class)->handle($worker, $clockPoint);

    $row = app(ListWorkDestinationsForWorkerAction::class)->handle($tenant, $worker)[0];

    expect($row->canStartVisit)->toBeTrue()
        ->and(fn () => app(StartWorkVisitAction::class)->handle($worker, $unit, 51.10, 3.73))
        ->toThrow(InvalidArgumentException::class, 'visit_unit_out_of_range');
});

it('voegt een gepubliceerde rooster-unit van vandaag toe als die nog ontbreekt', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => now()->toDateString(),
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'unit_id' => $unit->id,
        'unit_name' => $unit->name,
        'location_id' => $location->id,
    ]);

    $rows = app(ListWorkDestinationsForWorkerAction::class)->handle($tenant, $worker);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->unitId)->toBe((int) $unit->id);
});

it('ontdubbelt dezelfde unit uit taak, ronde en rooster', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'latitude' => 51.06,
        'longitude' => 3.74,
        'is_active' => true,
    ]);
    workDestTodayTask([$tenant, $worker], $unit, $location);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => null,
        'unit_id' => null,
        'approved_at' => now(),
        'is_recurring' => true,
    ]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unit->id, 'sort_order' => 0]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unitB->id, 'sort_order' => 1]);
    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $worker->internal_team_id,
        'status' => TaskStatus::New,
        'due_at' => now(),
    ]);
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => now()->toDateString(),
        'status' => PlannedShiftStatus::Published,
        'kind' => ShiftTypeKind::Work,
        'unit_id' => $unit->id,
        'location_id' => $location->id,
    ]);

    $rows = app(ListWorkDestinationsForWorkerAction::class)->handle($tenant, $worker);

    expect($rows)->toHaveCount(2)
        ->and(collect($rows)->pluck('unitId')->all())->toEqualCanonicalizing([(int) $unit->id, (int) $unitB->id]);
});

it('toont Vandaag en Zoek werkplek in de buurt na inklokken, zonder WorkVisit', function () {
    ensureTestEncryptionKey();
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    $worker->update([
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint->update(['qr_token' => 'today-dest-'.$tenant->id]);
    $location->update(['name' => 'Hotel De Brug']);
    $unit->update(['name' => 'Kamer 214']);
    workDestTodayTask([$tenant, $worker], $unit, $location);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->call('clockIn')
        ->assertSee(__('time.portal.today.title'), false)
        ->assertSeeHtml('wp-today-destination')
        ->assertSee('Hotel De Brug', false)
        ->assertSee('Kamer 214', false)
        ->assertSee(__('time.portal.today.navigate'), false)
        ->assertSee(__('time.portal.clock.find_nearby'), false)
        ->assertSee('google.com/maps/dir', false)
        ->assertDontSeeHtml('wire:click="navigate"');

    expect(WorkVisit::query()->count())->toBe(0);
});

it('neemt een undated inspectieronde mee als de volgende vervaldatum vandaag is', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Stop B',
        'latitude' => 51.06,
        'longitude' => 3.74,
        'is_active' => true,
    ]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => null,
        'unit_id' => null,
        'approved_at' => now(),
        'is_recurring' => true,
        'recurrence_next_due_at' => now()->endOfDay(),
    ]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unit->id, 'sort_order' => 0]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unitB->id, 'sort_order' => 1]);
    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $worker->internal_team_id,
        'status' => TaskStatus::InProgress,
        'scheduled_for' => null,
        'due_at' => null,
    ]);

    $rows = app(ListWorkDestinationsForWorkerAction::class)->handle($tenant, $worker);

    expect($rows)->toHaveCount(2)
        ->and(collect($rows)->pluck('unitId')->all())->toEqualCanonicalizing([(int) $unit->id, (int) $unitB->id]);
});

it('neemt een undated inspectieronde niet mee als de volgende vervaldatum morgen is', function () {
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'is_active' => true,
    ]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => null,
        'unit_id' => null,
        'approved_at' => now(),
        'is_recurring' => true,
        'recurrence_next_due_at' => now()->addDay()->endOfDay(),
    ]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unit->id, 'sort_order' => 0]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unitB->id, 'sort_order' => 1]);
    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $worker->internal_team_id,
        'status' => TaskStatus::InProgress,
        'scheduled_for' => null,
        'due_at' => null,
    ]);

    expect(app(ListWorkDestinationsForWorkerAction::class)->handle($tenant, $worker))->toBe([]);
});

it('toont inspectiestops op Vandaag en de ronde onder Open taken', function () {
    ensureTestEncryptionKey();
    [$tenant, $worker, $clockPoint, $location, $unit] = gpsVisitContext();
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Stop B',
        'latitude' => 51.06,
        'longitude' => 3.74,
        'is_active' => true,
    ]);
    $worker->update([
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint->update(['qr_token' => 'today-round-'.$tenant->id]);
    $location->update(['name' => 'Hotel De Brug']);
    $unit->update(['name' => 'Kamer 214']);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => null,
        'unit_id' => null,
        'approved_at' => now(),
        'is_recurring' => true,
        'description' => 'Poetssronde XYZ-hidden',
        'recurrence_next_due_at' => now()->endOfDay(),
    ]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unit->id, 'sort_order' => 0]);
    IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unitB->id, 'sort_order' => 1]);
    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $worker->internal_team_id,
        'status' => TaskStatus::InProgress,
        'scheduled_for' => now()->toDateString(),
        'due_at' => now()->endOfDay(),
        'description' => 'Poetssronde XYZ-hidden',
    ]);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->call('clockIn')
        ->assertSee(__('time.portal.today.title'), false)
        ->assertSee('Kamer 214', false)
        ->assertSee('Stop B', false)
        ->assertSee(__('time.portal.today.navigate'), false)
        ->assertSee('Poetssronde XYZ-hidden', false)
        ->assertDontSee(__('portal.team.read_only_hint'), false)
        ->assertDontSee(__('portal.worker.start_task'), false);
});

it('toont de lege Vandaag-kaart wanneer er geen geplande bestemmingen zijn', function () {
    ensureTestEncryptionKey();
    [$tenant, $worker, $clockPoint] = gpsVisitContext();
    $worker->update([
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint->update(['qr_token' => 'today-empty-'.$tenant->id]);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->call('clockIn')
        ->assertSee(__('time.portal.today.empty'), false)
        ->assertSee(__('time.portal.clock.find_nearby'), false);
});
