<?php

use App\Actions\Communication\EnsureAnnouncementTranslationSlotsAction;
use App\Actions\Communication\EnsureUnitTranslationSlotsAction;
use App\Actions\Communication\ImportUnitTranslationsAction;
use App\Actions\Communication\EnsureIssueTranslationSlotsAction;
use App\Actions\Communication\ImportAnnouncementTranslationsAction;
use App\Actions\Communication\ImportIssueTranslationsAction;
use App\Models\Announcement;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Unit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;

afterEach(fn () => Tenancy::forget());

it('lijst actieve mededelingen via API met vertalingen', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $announcement = Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Morgen onderhoud',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureAnnouncementTranslationSlotsAction::class)->handle($announcement);
    app(ImportAnnouncementTranslationsAction::class)->handle([
        [
            'announcement_id' => $announcement->id,
            'locale' => 'en',
            'description' => 'Maintenance tomorrow',
        ],
    ]);

    $token = $user->createToken('test', ['locations:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/announcements')
        ->assertOk()
        ->assertJsonPath('data.0.id', $announcement->id)
        ->assertJsonPath('data.0.original_language', 'nl')
        ->assertJsonPath('data.0.translations.en', 'Maintenance tomorrow');
});

it('toont één mededeling via API', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $announcement = Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Lift buiten gebruik',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    $token = $user->createToken('test', ['locations:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/announcements/{$announcement->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $announcement->id)
        ->assertJsonPath('data.description', 'Lift buiten gebruik');
});

it('filtert inactieve mededelingen uit API-lijst', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'is_active' => false,
    ]);

    $token = $user->createToken('test', ['locations:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/announcements')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('isoleert mededelingen per tenant via API', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $locationB = Location::factory()->create(['tenant_id' => $tenantB->id]);

    Announcement::factory()->create([
        'tenant_id' => $tenantB->id,
        'location_id' => $locationB->id,
        'is_active' => true,
    ]);

    $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => User::ROLE_ADMIN]);
    $token = $userA->createToken('test', ['locations:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/announcements')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('stuurt issue.translation_imported webhook met geldige HMAC', function () {
    Http::fake(['https://hooks.test/*' => Http::response('ok', 200)]);

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Lekkende kraan',
        'original_language' => 'nl',
        'approved_at' => now(),
        'approved_by' => $user->id,
    ]);

    app(EnsureIssueTranslationSlotsAction::class)->handle($issue);

    $endpoint = WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://hooks.test/issues',
        'events' => ['issue.translation_imported'],
    ]);

    app(ImportIssueTranslationsAction::class)->handle([
        [
            'issue_id' => $issue->id,
            'locale' => 'en',
            'description' => 'Leaking faucet',
        ],
    ]);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($endpoint, $issue) {
        if ($request->url() !== $endpoint->url) {
            return false;
        }

        $payload = json_decode($request->body(), true);

        return ($request->header('X-WinProx-Event')[0] ?? '') === 'issue.translation_imported'
            && ($payload['payload']['issue_id'] ?? null) === $issue->id
            && ($payload['payload']['locale'] ?? null) === 'en';
    });
});

it('filtert webhook subscriptions op vertaling-event type', function () {
    Http::fake(['https://hooks.test/*' => Http::response('ok', 200)]);

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $announcement = Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Test',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureAnnouncementTranslationSlotsAction::class)->handle($announcement);

    WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://hooks.test/a',
        'events' => ['announcement.translation_imported'],
    ]);

    WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://hooks.test/b',
        'events' => ['issue.created'],
    ]);

    app(ImportAnnouncementTranslationsAction::class)->handle([
        [
            'announcement_id' => $announcement->id,
            'locale' => 'en',
            'description' => 'Test EN',
        ],
    ]);

    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.test/a');
    Http::assertNotSent(fn ($request) => $request->url() === 'https://hooks.test/b');
});

it('stuurt announcement.translation_imported webhook met geldige HMAC', function () {
    Http::fake(['https://hooks.test/*' => Http::response('ok', 200)]);

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $announcement = Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Morgen onderhoud',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureAnnouncementTranslationSlotsAction::class)->handle($announcement);

    $endpoint = WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://hooks.test/winprox',
        'events' => ['announcement.translation_imported'],
    ]);

    app(ImportAnnouncementTranslationsAction::class)->handle([
        [
            'announcement_id' => $announcement->id,
            'locale' => 'en',
            'description' => 'Maintenance tomorrow',
        ],
    ]);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($endpoint, $announcement) {
        if ($request->url() !== $endpoint->url) {
            return false;
        }

        $timestamp = $request->header('X-WinProx-Timestamp')[0] ?? '';
        $signature = $request->header('X-WinProx-Signature')[0] ?? '';
        $body = $request->body();
        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $endpoint->secret);

        $payload = json_decode($body, true);

        return hash_equals($expected, $signature)
            && ($request->header('X-WinProx-Event')[0] ?? '') === 'announcement.translation_imported'
            && ($payload['payload']['announcement_id'] ?? null) === $announcement->id
            && ($payload['payload']['locale'] ?? null) === 'en';
    });
});

it('staat vertaling-webhook-events toe bij endpoint opslaan', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $endpoint = app(\App\Actions\Webhooks\StoreWebhookEndpointAction::class)->handle([
        'url' => 'https://hooks.test/translations',
        'events' => [
            'issue.translation_imported',
            'announcement.translation_imported',
            'unit.translation_imported',
            'task.translation_imported',
            'invalid.event',
        ],
    ], $tenant->id);

    expect($endpoint->events)->toBe([
        'issue.translation_imported',
        'announcement.translation_imported',
        'unit.translation_imported',
        'task.translation_imported',
    ]);
});

it('stuurt unit.translation_imported webhook', function () {
    Http::fake(['https://hooks.test/*' => Http::response('ok', 200)]);

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Lift A',
        'description' => 'Verdieping 2',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureUnitTranslationSlotsAction::class)->handle($unit);

    WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://hooks.test/units',
        'events' => ['unit.translation_imported'],
    ]);

    app(ImportUnitTranslationsAction::class)->handle([
        [
            'unit_id' => $unit->id,
            'locale' => 'en',
            'name' => 'Elevator A',
            'description' => 'Floor 2',
        ],
    ]);

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => ($request->header('X-WinProx-Event')[0] ?? '') === 'unit.translation_imported');
});

it('stuurt task.translation_imported webhook', function () {
    Http::fake(['https://hooks.test/*' => Http::response('ok', 200)]);

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'original_language' => 'nl',
        'approved_at' => now(),
    ]);

    $task = app(\App\Actions\Tasks\CreateTaskAction::class)->handle($issue, $team->id, description: 'Vervang pakking');

    WebhookEndpoint::factory()->create([
        'tenant_id' => $tenant->id,
        'url' => 'https://hooks.test/tasks',
        'events' => ['task.translation_imported'],
        'is_active' => true,
    ]);

    app(\App\Actions\Communication\ImportTaskTranslationsAction::class)->handle([
        [
            'task_id' => $task->id,
            'locale' => 'en',
            'description' => 'Replace gasket',
        ],
    ]);

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => ($request->header('X-WinProx-Event')[0] ?? '') === 'task.translation_imported');
});

it('bevat vertaling-webhook-events in AVAILABLE_EVENTS', function () {
    expect(\App\Models\WebhookEndpoint::AVAILABLE_EVENTS)->toContain(
        'issue.translation_imported',
        'announcement.translation_imported',
        'unit.translation_imported',
        'task.translation_imported',
    );
});
