<?php

use App\Actions\Issues\ApproveIssueAction;
use App\Actions\Issues\CreateIssueAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Enums\TaskStatus;
use App\Livewire\Issues\Index as IssueIndex;
use App\Livewire\Issues\Show as IssueShow;
use App\Livewire\Tasks\Index as TaskIndex;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('verbergt taken in beheer tot de melding is goedgekeurd', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Facility Support']);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    Tenancy::actAs($tenant->id);

    $issue = app(CreateIssueAction::class)->handle(
        [
            'description' => 'QR melding wacht op controle',
            'source' => 'qr',
            'location_id' => $location->id,
            'unit_id' => $unit->id,
        ],
        [$team->id],
    );

    Livewire::actingAs($user)
        ->test(IssueShow::class, ['issue' => $issue])
        ->assertSee(__('issues.show.tasks_hidden_until_approved'))
        ->assertDontSee('Facility Support')
        ->assertDontSee(__('common.button.edit'));

    Livewire::actingAs($user)
        ->test(TaskIndex::class)
        ->assertViewHas('groups', fn (array $groups) => collect($groups)->flatMap->tasks->isEmpty());

    $this->actingAs($user)
        ->get(route('tasks.show', $issue->tasks->first()))
        ->assertForbidden();

    expect(fn () => app(UpdateTaskStatusAction::class)->handle(
        $issue->tasks->first(),
        TaskStatus::InProgress,
    ))->toThrow(ValidationException::class);

    app(ApproveIssueAction::class)->handle($issue, $user);

    Livewire::actingAs($user)
        ->test(IssueShow::class, ['issue' => $issue->fresh()])
        ->assertSee(__('issues.show.add_task_button'))
        ->assertSee(__('common.button.edit'));

    $this->actingAs($user)
        ->get(route('tasks.show', $issue->tasks->first()))
        ->assertOk();
});

it('toont ongekeurde meldingen in een apart blok bovenaan de lijst', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    Tenancy::actAs($tenant->id);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Wacht op controle',
        'status' => TaskStatus::New,
        'approved_at' => null,
    ]);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Goedgekeurde melding',
        'status' => TaskStatus::New,
        'approved_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(IssueIndex::class)
        ->assertViewHas('groups', function (array $groups) {
            return ($groups[0]['kind'] ?? null) === 'pending'
                && $groups[0]['issues']->first()->description === 'Wacht op controle';
        })
        ->assertSee(__('issues.pending_review'), false);
});
