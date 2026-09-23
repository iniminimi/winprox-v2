<?php

use App\Actions\Briefing\BuildMorningBriefingAction;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('build morning briefing action levert taken voor team en datum', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create(['tenant_id' => $tenant->id, 'approved_at' => now()]);
    $date = now()->addDay()->toDateString();

    Tenancy::actAs($tenant->id);

    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'scheduled_for' => $date,
        'status' => \App\Enums\TaskStatus::New,
        'description' => 'Testtaak briefing',
    ]);

    $briefing = app(BuildMorningBriefingAction::class)->handle(
        $tenant,
        $user,
        $team->id,
        \Illuminate\Support\Carbon::parse($date),
        false,
    );

    expect($briefing->lineCount)->toBe(1);
    $line = $briefing->unitLines->concat($briefing->generalLines)->first();
    expect($line?->summary)->toContain('Testtaak briefing');
});

it('toont briefing filter en taken na team en datum', function () {
    $tenant = Tenant::factory()->create(['name' => 'Gemeente Knokke-Heist']);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Team Schoonmaak']);
    InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Team Techniek']);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Hoofdgebouw']);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Koffiemachine',
    ]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'description' => 'Schoonmaken aub.',
        'approved_at' => now(),
    ]);

    $date = now()->addDay()->toDateString();

    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'scheduled_for' => $date,
        'status' => \App\Enums\TaskStatus::New,
        'description' => 'Lift storing oplossen',
    ]);

    Tenancy::actAs($tenant->id);

    $this->actingAs($user)
        ->get(route('briefing.print'))
        ->assertOk()
        ->assertSee(__('briefing.select_team'), false)
        ->assertSee(__('briefing.apply_filters'), false)
        ->assertSee('Team Techniek', false);

    $this->actingAs($user)
        ->get(route('briefing.print', ['team' => $team->id, 'date' => $date]))
        ->assertOk()
        ->assertSee('Team Schoonmaak', false)
        ->assertSee('Koffiemachine', false)
        ->assertSee('Lift storing oplossen', false);
});

it('briefing accepteert internal_team_id alias', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Team A']);

    Tenancy::actAs($tenant->id);

    $this->actingAs($user)
        ->get(route('briefing.print', ['internal_team_id' => $team->id]))
        ->assertOk()
        ->assertSee('Team A', false);
});

it('toont inspectieronde-locaties in stopvolgorde, niet als algemene zone', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $zebra = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Zebra-werf',
    ]);
    $alpha = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Alpha-klant',
    ]);
    $unitZebra = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $zebra->id,
        'name' => 'Hele locatie',
        'is_site_unit' => true,
        'is_active' => true,
    ]);
    $unitAlpha = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $alpha->id,
        'name' => 'Hele locatie',
        'is_site_unit' => true,
        'is_active' => true,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => null,
        'unit_id' => null,
        'description' => 'Schoonmaakronde Brugge',
        'approved_at' => now(),
        'is_recurring' => true,
    ]);
    \App\Models\IssueRoundStop::query()->create([
        'issue_id' => $issue->id,
        'unit_id' => $unitZebra->id,
        'sort_order' => 0,
    ]);
    \App\Models\IssueRoundStop::query()->create([
        'issue_id' => $issue->id,
        'unit_id' => $unitAlpha->id,
        'sort_order' => 1,
    ]);

    $date = now()->addDay()->startOfDay();
    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'scheduled_for' => $date->toDateString(),
        'due_at' => $date,
        'status' => \App\Enums\TaskStatus::InProgress,
        'is_recurring_cycle' => true,
        'description' => null,
    ]);

    Tenancy::actAs($tenant->id);
    app()->setLocale('nl');

    $briefing = app(BuildMorningBriefingAction::class)->handle(
        $tenant,
        $user,
        $team->id,
        $date,
        false,
    );

    expect($briefing->roundLines)->toHaveCount(2)
        ->and($briefing->roundLines->pluck('locationLabel')->all())->toBe(['Zebra-werf', 'Alpha-klant'])
        ->and($briefing->roundLines->first()?->summary)->toContain('Schoonmaakronde Brugge')
        ->and($briefing->roundLines->first()?->summary)->toContain(__('briefing.recurring_badge'))
        ->and($briefing->generalLines)->toBeEmpty()
        ->and($briefing->unitLines)->toBeEmpty();

    $this->actingAs($user)
        ->get(route('briefing.print', ['team' => $team->id, 'date' => $date->toDateString()]))
        ->assertOk()
        ->assertSee(__('briefing.section_rounds'), false)
        ->assertSeeInOrder(['Zebra-werf', 'Alpha-klant'])
        ->assertDontSee(__('briefing.general_area_fallback'), false);
});

it('dedupeert meerdere stops op dezelfde locatie in de briefing', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hoofdgebouw',
    ]);
    $unitA = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Keuken',
        'is_active' => true,
    ]);
    $unitB = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Sanitair',
        'is_active' => true,
    ]);
    $other = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Annex',
    ]);
    $unitC = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $other->id,
        'name' => 'Berging',
        'is_active' => true,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => null,
        'unit_id' => null,
        'description' => 'Controle-ronde',
        'approved_at' => now(),
        'is_recurring' => false,
    ]);
    \App\Models\IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unitA->id, 'sort_order' => 0]);
    \App\Models\IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unitB->id, 'sort_order' => 1]);
    \App\Models\IssueRoundStop::query()->create(['issue_id' => $issue->id, 'unit_id' => $unitC->id, 'sort_order' => 2]);

    $date = now()->startOfDay();
    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'scheduled_for' => $date->toDateString(),
        'due_at' => $date,
        'status' => \App\Enums\TaskStatus::InProgress,
    ]);

    Tenancy::actAs($tenant->id);

    $briefing = app(BuildMorningBriefingAction::class)->handle(
        $tenant,
        $user,
        $team->id,
        $date,
        false,
    );

    expect($briefing->roundLines->pluck('locationLabel')->all())->toBe(['Hoofdgebouw', 'Annex'])
        ->and($briefing->roundLines->first()?->summary)->toBe('Controle-ronde')
        ->and($briefing->roundLines->first()?->summary)->not->toContain(__('briefing.recurring_badge'));
});
