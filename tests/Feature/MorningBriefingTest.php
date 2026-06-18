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
        'note' => 'Testtaak briefing',
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
        'note' => 'Lift storing oplossen',
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
