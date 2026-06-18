<?php

use App\Actions\Communication\CountPendingIssueTranslationsAction;
use App\Actions\Communication\RunTranslationSyncPipelineAction;
use App\Actions\Communication\StartTranslationSyncAction;
use App\Actions\Communication\TranslateExportItemsAction;
use App\Contracts\TranslationSyncRemoteClient;
use App\Enums\TranslationSyncPhase;
use App\Jobs\RunTranslationSyncJob;
use App\Livewire\Platform\TranslationSync as PlatformTranslationSync;
use App\Livewire\Platform\Tenants as PlatformTenants;
use App\Models\User;
use App\Models\Issue;
use App\Models\IssueTranslation;
use App\Models\Tenant;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Translation\TranslationSyncStatusStore;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\FakeTranslationProvider;
use Tests\Support\FakeTranslationSyncRemoteClient;

beforeEach(function () {
    config([
        'translation_sync.enabled' => true,
        'translation_sync.ssh_host' => 'example.test',
        'translation_sync.ssh_user' => 'deploy',
        'translation_sync.remote_path' => '/var/www/winprox',
        'translation_sync.work_dir' => storage_path('framework/testing/translation-sync'),
        'translation_sync.status_path' => 'translation-sync/test-status.json',
        'ollama.enabled' => true,
    ]);

    app()->instance(TranslationProviderInterface::class, new FakeTranslationProvider);
});

it('vertaalt export-items via de provider', function () {
    $items = app(TranslateExportItemsAction::class)->handle([
        [
            'issue_id' => 12,
            'locale' => 'nl',
            'source_text' => 'Broken window',
        ],
    ]);

    expect($items)->toBe([
        ['issue_id' => 12, 'locale' => 'nl', 'text' => '[nl] Broken window'],
    ]);
});

it('doorloopt de vertaal-sync pipeline met remote fake', function () {
    $fake = new FakeTranslationSyncRemoteClient;
    $fake->exportItems = [
        [
            'issue_id' => 5,
            'locale' => 'nl',
            'source_text' => 'Light broken',
        ],
        [
            'issue_id' => 6,
            'locale' => 'fr',
            'source_text' => 'Door stuck',
        ],
    ];
    app()->instance(TranslationSyncRemoteClient::class, $fake);

    $result = app(RunTranslationSyncPipelineAction::class)->handle(1);

    expect($result)->toBe(['total' => 2, 'imported' => 2])
        ->and($fake->exportRuns)->toBe(1)
        ->and($fake->importRuns)->toBe(1)
        ->and($fake->uploadedImportPath)->not->toBeNull();

    $status = app(TranslationSyncStatusStore::class)->read();
    expect($status['phase'] ?? null)->toBe(TranslationSyncPhase::Completed->value)
        ->and($status['imported'] ?? null)->toBe(2);
});

it('zet een vertaal-run in de wachtrij voor superuser', function () {
    Queue::fake();

    $user = User::factory()->create(['is_superuser' => true, 'tenant_id' => null]);

    app(StartTranslationSyncAction::class)->handle((int) $user->id);

    Queue::assertPushed(RunTranslationSyncJob::class);
});

it('toont de vertaal-sync pagina voor superuser', function () {
    $user = User::factory()->create(['is_superuser' => true, 'tenant_id' => null]);

    Livewire::actingAs($user)
        ->test(PlatformTranslationSync::class)
        ->assertSee(__('platform.translation_sync.start'));
});

it('vult vertaal-slots voor bestaande goedgekeurde meldingen', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Oude melding',
        'original_language' => 'nl',
        'approved_at' => now(),
        'approved_by' => $user->id,
    ]);

    expect(IssueTranslation::query()->where('issue_id', $issue->id)->count())->toBe(0);

    $result = app(\App\Actions\Communication\BackfillIssueTranslationSlotsAction::class)->handle();

    expect($result['issues'])->toBeGreaterThanOrEqual(1)
        ->and($result['slots_created'])->toBe(3)
        ->and(IssueTranslation::query()->where('issue_id', $issue->id)->count())->toBe(3);
});

it('telt pending vertalingen voor goedgekeurde meldingen', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'approved_at' => now(),
        'approved_by' => $user->id,
        'original_language' => 'nl',
    ]);

    expect(app(CountPendingIssueTranslationsAction::class)->handle())->toBe(0);

    app(\App\Actions\Communication\BackfillIssueTranslationSlotsAction::class)->handle();

    expect(app(CountPendingIssueTranslationsAction::class)->handle())->toBe(3);
});

it('toont vertaal-herinnering op platform organisaties voor superuser op productie', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $user = User::factory()->create(['is_superuser' => true, 'tenant_id' => null]);

    Livewire::actingAs($user)
        ->test(PlatformTenants::class)
        ->assertSee(__('platform.translation_sync.reminder_title'))
        ->assertSee(__('platform.translation_sync.reminder_body'));
});

it('toont lokale vertaal-herinnering zonder server-telling', function () {
    $this->app->detectEnvironment(fn () => 'local');

    $user = User::factory()->create(['is_superuser' => true, 'tenant_id' => null]);

    Livewire::actingAs($user)
        ->test(PlatformTenants::class)
        ->assertSee(__('platform.translation_sync.reminder_title'))
        ->assertSee(__('platform.translation_sync.reminder_local'))
        ->assertDontSee(__('platform.translation_sync.reminder_body'));
});

it('toont servermelding op vertaalpagina zonder lokale sync-config', function () {
    config(['translation_sync.enabled' => false]);

    $user = User::factory()->create(['is_superuser' => true, 'tenant_id' => null]);

    Livewire::actingAs($user)
        ->test(PlatformTranslationSync::class)
        ->assertSee(__('platform.translation_sync.server_only_message'))
        ->assertDontSee(__('platform.translation_sync.workflow_title'));
});
