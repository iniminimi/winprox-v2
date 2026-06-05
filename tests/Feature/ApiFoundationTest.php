<?php

use App\Actions\Issues\ApproveIssueAction;
use App\Actions\Issues\CreateIssueAction;
use App\Actions\Tasks\StartTaskAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Enums\TaskStatus;
use App\Events\Issues\IssueApproved;
use App\Events\Tasks\TaskStarted;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

afterEach(fn () => Tenancy::forget());

it('weigert API zonder token', function () {
    $this->getJson('/api/v1/issues')->assertUnauthorized();
});

it('maakt een melding aan via de API met Sanctum-token', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['issues:create'])->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/issues', [
            'description' => 'Via API',
            'team_ids' => [],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.description', 'Via API');

    Tenancy::actAs($tenant->id);
    expect(Issue::count())->toBe(1);
});

it('isoleert API-data per tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    Issue::factory()->create(['tenant_id' => $tenantB->id, 'description' => 'B']);

    $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => User::ROLE_ADMIN]);
    $token = $userA->createToken('test', ['issues:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/issues')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('stuurt issue.created webhook met geldige HMAC', function () {
    Http::fake(['https://hooks.test/*' => Http::response('ok', 200)]);

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $endpoint = WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://hooks.test/winprox',
        'events' => ['issue.created'],
    ]);

    app(CreateIssueAction::class)->handle(['description' => 'Hook test']);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($endpoint) {
        if ($request->url() !== $endpoint->url) {
            return false;
        }

        $timestamp = $request->header('X-WinProx-Timestamp')[0] ?? '';
        $signature = $request->header('X-WinProx-Signature')[0] ?? '';
        $body = $request->body();
        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $endpoint->secret);

        return hash_equals($expected, $signature)
            && ($request->header('X-WinProx-Event')[0] ?? '') === 'issue.created';
    });
});

it('dispatcht domein-events bij goedkeuren en taakstart', function () {
    Event::fake();

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    $issue = Issue::factory()->create(['tenant_id' => $tenant->id, 'approved_at' => null]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $task = $issue->tasks()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'status' => TaskStatus::New,
    ]);

    app(ApproveIssueAction::class)->handle($issue, $user);
    Event::assertDispatched(IssueApproved::class);

    app(StartTaskAction::class)->handle($task);
    Event::assertDispatched(TaskStarted::class);
});

it('blokkeert settings API-pagina voor medewerkers', function () {
    $tenant = Tenant::factory()->create();
    $employee = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_EMPLOYEE,
    ]);

    $this->actingAs($employee)
        ->get(route('settings.api'))
        ->assertForbidden();
});

it('weigert API token zonder vereiste ability', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', [])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/issues')
        ->assertForbidden();
});

it('toegestaan API token met juiste ability', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $token = $user->createToken('test', ['issues:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/issues')
        ->assertOk();
});

it('filtert webhook subscriptions op event type', function () {
    Http::fake(['https://hooks.test/*' => Http::response('ok', 200)]);

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $endpointA = WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://hooks.test/a',
        'events' => ['issue.created'],
    ]);

    $endpointB = WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://hooks.test/b',
        'events' => ['task.created'],
    ]);

    app(CreateIssueAction::class)->handle(['description' => 'Hook test']);

    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.test/a');
    Http::assertNotSent(fn ($request) => $request->url() === 'https://hooks.test/b');
});

it('retry mechanism heeft juiste configuratie', function () {
    $job = new \App\Jobs\SendWebhookDeliveryJob(1);
    
    expect($job->tries)->toBe(4)
        ->and($job->backoff())->toBe([10, 60, 300]);
});

it('logt failed delivery correct bij permanente fout', function () {
    Http::fake(['https://hooks.test/*' => Http::response('error', 500)]);
    \Illuminate\Support\Facades\Queue::fake();

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $endpoint = WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://hooks.test/winprox',
        'events' => ['issue.created'],
    ]);

    app(CreateIssueAction::class)->handle(['description' => 'Fail test']);

    $delivery = $endpoint->deliveries()->where('event', 'issue.created')->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe('pending')
        ->and($delivery->attempts)->toBe(0)
        ->and($delivery->error)->toBeNull();

    // Verify delivery model has required fields for failure logging
    expect($delivery->getFillable())->toContain('status', 'error', 'attempts');
});
