<?php

use App\Livewire\Issues\Show as IssueShow;
use App\Models\Issue;
use App\Models\Tenant;
use App\Models\User;
use App\Enums\RecurrenceIntervalUnit;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('pauses and resumes recurrence on issue detail', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_interval_value' => 1,
        'recurrence_interval_unit' => RecurrenceIntervalUnit::Month,
        'recurrence_lead_days' => 14,
        'recurrence_active' => true,
        'recurrence_next_due_at' => now()->addMonth(),
        'recurrence_paused_at' => null,
    ]);

    Livewire::actingAs($user)
        ->test(IssueShow::class, ['issue' => $issue])
        ->call('toggleRecurrencePause')
        ->assertHasNoErrors();

    expect($issue->fresh()->recurrence_paused_at)->not->toBeNull();

    Livewire::actingAs($user)
        ->test(IssueShow::class, ['issue' => $issue->fresh()])
        ->call('toggleRecurrencePause')
        ->assertHasNoErrors();

    expect($issue->fresh()->recurrence_paused_at)->toBeNull();
});
