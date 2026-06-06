<?php

use App\Actions\Tasks\CreateTaskAction;
use App\Actions\Tasks\UpdateTaskPriorityAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('enum label() returns correct translation key', function () {
    expect(TaskPriority::Prio1->label())->toBe(__('tasks.priority.prio_1'))
        ->and(TaskPriority::Prio2->label())->toBe(__('tasks.priority.prio_2'))
        ->and(TaskPriority::Prio3->label())->toBe(__('tasks.priority.prio_3'))
        ->and(TaskPriority::Prio4->label())->toBe(__('tasks.priority.prio_4'));
});

it('enum badgeClass() returns correct CSS classes', function () {
    expect(TaskPriority::Prio1->badgeClass())->toBe('wp-badge-critical')
        ->and(TaskPriority::Prio2->badgeClass())->toBe('wp-badge-danger')
        ->and(TaskPriority::Prio3->badgeClass())->toBe('wp-badge-secondary')
        ->and(TaskPriority::Prio4->badgeClass())->toBe('wp-badge-info');
});

it('enum sortOrder() returns correct numeric values', function () {
    expect(TaskPriority::Prio1->sortOrder())->toBe(1)
        ->and(TaskPriority::Prio2->sortOrder())->toBe(2)
        ->and(TaskPriority::Prio3->sortOrder())->toBe(3)
        ->and(TaskPriority::Prio4->sortOrder())->toBe(4);
});

it('new tasks get default priority prio_3 from database', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create(['tenant_id' => $tenant->id]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
    ]);

    expect($task->priority)->toBe(TaskPriority::Prio3);
});

it('model casts priority to TaskPriority enum', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create(['tenant_id' => $tenant->id]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'priority' => 'prio_2',
    ]);

    expect($task->priority)->toBeInstanceOf(TaskPriority::class)
        ->and($task->priority)->toBe(TaskPriority::Prio2)
        ->and($task->priority->value)->toBe('prio_2');
});

it('CreateTaskAction accepts priority parameter with default prio_3', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create(['tenant_id' => $tenant->id, 'source' => 'qr']);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    // Default priority
    $task1 = app(CreateTaskAction::class)->handle($issue, $team->id);
    expect($task1->priority)->toBe(TaskPriority::Prio3);

    // Explicit priority
    $task2 = app(CreateTaskAction::class)->handle($issue, $team->id, TaskStatus::New, TaskPriority::Prio1);
    expect($task2->priority)->toBe(TaskPriority::Prio1);
});

it('UpdateTaskPriorityAction changes priority correctly', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create(['tenant_id' => $tenant->id]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'priority' => TaskPriority::Prio4,
    ]);

    $updated = app(UpdateTaskPriorityAction::class)->handle($task, TaskPriority::Prio2, $tenant->id);

    expect($updated->priority)->toBe(TaskPriority::Prio2);
});

it('UpdateTaskPriorityAction cannot change task from another tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Tenancy::actAs($tenantA->id);

    $issue = Issue::factory()->create(['tenant_id' => $tenantB->id]);
    $task = Task::factory()->create([
        'tenant_id' => $tenantB->id,
        'issue_id' => $issue->id,
        'priority' => TaskPriority::Prio4,
    ]);

    // Probeer taak van tenantB te wijzigen vanuit tenantA context
    // Dit zou moeten falen door tenant scoping in het model
    $updated = app(UpdateTaskPriorityAction::class)->handle($task, TaskPriority::Prio2, $tenantA->id);

    // De taak zou niet gewijzigd moeten zijn (of een exception moeten gooien)
    // Omdat we tenantId meegeven aan de Action, zou deze moeten checken
    expect($updated->priority)->toBe(TaskPriority::Prio4);
})->throws(\Exception::class);

it('unauthorized user cannot change priority via policy', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'worker']); // Worker heeft geen rechten
    $this->actingAs($user);

    $issue = Issue::factory()->create(['tenant_id' => $tenant->id]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'priority' => TaskPriority::Prio4,
    ]);

    $this->assertFalse($user->can('update', $task));
});

it('authorized admin can change priority via policy', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
    $this->actingAs($user);

    $issue = Issue::factory()->create(['tenant_id' => $tenant->id]);
    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'priority' => TaskPriority::Prio4,
    ]);

    $this->assertTrue($user->can('update', $task));
});

it('tasks sort by priority then created_at returns correct order', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create(['tenant_id' => $tenant->id]);

    // Create tasks with different priorities and timestamps
    $task1 = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'priority' => TaskPriority::Prio3,
        'created_at' => now()->subMinutes(10),
    ]);

    $task2 = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'priority' => TaskPriority::Prio1,
        'created_at' => now()->subMinutes(5),
    ]);

    $task3 = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'priority' => TaskPriority::Prio2,
        'created_at' => now()->subMinutes(3),
    ]);

    $task4 = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'priority' => TaskPriority::Prio4,
        'created_at' => now()->subMinutes(1),
    ]);

    // Sort by priority (sortOrder) then created_at using case statement for SQLite compatibility
    $sorted = Task::query()
        ->orderByRaw('CASE priority WHEN "prio_1" THEN 1 WHEN "prio_2" THEN 2 WHEN "prio_3" THEN 3 WHEN "prio_4" THEN 4 ELSE 5 END')
        ->orderBy('created_at')
        ->get();

    expect($sorted[0]->id)->toBe($task2->id) // Prio 1
        ->and($sorted[1]->id)->toBe($task3->id) // Prio 2
        ->and($sorted[2]->id)->toBe($task1->id) // Prio 3
        ->and($sorted[3]->id)->toBe($task4->id); // Prio 4
});
