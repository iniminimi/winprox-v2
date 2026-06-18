<?php

use App\Actions\Communication\RunTranslationSyncPipelineAction;
use App\Actions\Communication\StartTranslationSyncAction;
use App\Actions\Communication\TranslateExportItemsAction;
use App\Contracts\TranslationSyncRemoteClient;
use App\Enums\TranslationSyncPhase;
use App\Jobs\RunTranslationSyncJob;
use App\Livewire\Platform\TranslationSync as PlatformTranslationSync;
use App\Models\User;
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
