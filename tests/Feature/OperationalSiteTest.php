<?php

use App\Livewire\Platform\Help as PlatformHelp;
use App\Models\HelpChatKnowledgeBaseEntry;
use App\Models\HelpChatUnansweredQuestion;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('toont volledige welcome landingspagina', function () {
    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee(__('welcome.hero.badge'))
        ->assertSee(__('welcome.pillars.eyebrow'))
        ->assertSee(__('welcome.flow.steps.0.title'))
        ->assertSee(__('welcome.closing.cta_start'));
});

it('briefing filtert op datum en team', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $otherTeam = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    $date = now()->addDays(2)->toDateString();
    $issue = Issue::factory()->create(['tenant_id' => $tenant->id, 'approved_at' => now()]);

    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $team->id,
        'scheduled_for' => $date,
        'status' => \App\Enums\TaskStatus::New,
        'description' => 'Alleen team A',
    ]);

    Task::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'internal_team_id' => $otherTeam->id,
        'scheduled_for' => $date,
        'status' => \App\Enums\TaskStatus::New,
        'description' => 'Alleen team B',
    ]);

    $this->actingAs($user)
        ->get(route('briefing.print', ['date' => $date, 'team' => $team->id]))
        ->assertOk()
        ->assertSee($team->name)
        ->assertSee('Alleen team A')
        ->assertDontSee('Alleen team B');
});

it('superuser kan kennisbank beheren', function () {
    $super = User::factory()->superuser()->create();

    Livewire::actingAs($super)
        ->test(PlatformHelp::class)
        ->call('openCreateKb')
        ->set('kbLocale', 'nl')
        ->set('kbMatchKey', 'test_faq')
        ->set('kbPatterns', "hoe werkt qr\nqr melden")
        ->set('kbAnswer', 'Scan de code op de unit.')
        ->call('saveKb')
        ->assertHasNoErrors();

    expect(HelpChatKnowledgeBaseEntry::query()->where('match_key', 'test_faq')->exists())->toBeTrue();
});

it('superuser kan onbeantwoorde vraag verwijderen', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $super = User::factory()->superuser()->create();

    $row = HelpChatUnansweredQuestion::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'locale' => 'nl',
        'question' => 'Testvraag assistent',
    ]);

    Livewire::actingAs($super)
        ->test(PlatformHelp::class)
        ->call('dismissUnanswered', $row->id);

    expect(HelpChatUnansweredQuestion::query()->find($row->id))->toBeNull();
});
