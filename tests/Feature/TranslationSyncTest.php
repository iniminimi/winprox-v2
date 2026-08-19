<?php

use App\Actions\Communication\CancelTranslationSyncAction;
use App\Actions\Communication\CountPendingIssueTranslationsAction;
use App\Actions\Communication\ReadTranslationSyncStatusAction;
use App\Actions\Communication\ResetTranslationSyncStatusAction;
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
use App\Support\Translation\TranslationSyncCancellation;
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
        ['issue_id' => 12, 'locale' => 'nl', 'description' => '[nl] Broken window'],
    ]);
});

it('vertaalt unit export-items via de provider', function () {
    $items = app(\App\Actions\Communication\TranslateExportItemsAction::class)->handle([
        [
            'unit_id' => 9,
            'locale' => 'fr',
            'source_name' => 'Graafmachine',
            'source_description' => 'Zone B',
        ],
    ]);

    expect($items)->toBe([
        [
            'locale' => 'fr',
            'unit_id' => 9,
            'name' => '[fr] Graafmachine',
            'description' => '[fr] Zone B',
        ],
    ]);
});

it('vertaalt mededeling export-items via de provider', function () {
    $items = app(TranslateExportItemsAction::class)->handle([
        [
            'announcement_id' => 7,
            'locale' => 'fr',
            'source_text' => 'Travaux demain',
        ],
    ]);

    expect($items)->toBe([
        ['announcement_id' => 7, 'locale' => 'fr', 'description' => '[fr] Travaux demain'],
    ]);
});

it('vertaalt locatie export-items via de provider', function () {
    $items = app(TranslateExportItemsAction::class)->handle([
        [
            'location_id' => 4,
            'locale' => 'en',
            'source_name' => 'Hoofddepot',
        ],
    ]);

    expect($items)->toBe([
        ['locale' => 'en', 'location_id' => 4, 'name' => '[en] Hoofddepot'],
    ]);
});

it('kapt te lange korte naam-vertalingen af voor locatie-sync', function () {
    app()->instance(TranslationProviderInterface::class, new class implements TranslationProviderInterface
    {
        public function translate(string $text, string $targetLanguage): string
        {
            return "  {$targetLanguage}  ".str_repeat('x', 400);
        }
    });

    $items = app(TranslateExportItemsAction::class)->handle([
        [
            'location_id' => 4,
            'locale' => 'en',
            'source_name' => 'Hoofddepot',
        ],
    ]);

    expect($items)->toHaveCount(1)
        ->and($items[0]['name'])->toHaveLength(255)
        ->and($items[0]['name'])->not->toContain('  ');
});

it('vertaalt categorie export-items via de provider', function () {
    $items = app(TranslateExportItemsAction::class)->handle([
        [
            'category_id' => 11,
            'locale' => 'de',
            'source_name' => 'Techniek',
        ],
    ]);

    expect($items)->toBe([
        ['category_id' => 11, 'locale' => 'de', 'name' => '[de] Techniek'],
    ]);
});

it('vertaalt team export-items via de provider', function () {
    $items = app(TranslateExportItemsAction::class)->handle([
        [
            'internal_team_id' => 15,
            'locale' => 'fr',
            'source_name' => 'Technische dienst',
        ],
    ]);

    expect($items)->toBe([
        ['internal_team_id' => 15, 'locale' => 'fr', 'name' => '[fr] Technische dienst'],
    ]);
});

it('faalt wanneer geen exportregels vertaald werden', function () {
    $fake = new FakeTranslationSyncRemoteClient;
    $fake->exportItems = [
        [
            'issue_id' => 1,
            'locale' => 'en',
            'source_text' => '',
        ],
    ];
    app()->instance(TranslationSyncRemoteClient::class, $fake);

    expect(fn () => app(RunTranslationSyncPipelineAction::class)->handle(1))
        ->toThrow(RuntimeException::class, __('platform.translation_sync.error_nothing_translated'));

    $status = app(TranslationSyncStatusStore::class)->read();
    expect($status['phase'] ?? null)->toBe(TranslationSyncPhase::Failed->value);
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

it('importeert de vertaal-sync per reeks', function () {
    config(['translation_sync.batch_size' => 2]);

    $fake = new FakeTranslationSyncRemoteClient;
    $fake->exportItems = [
        ['issue_id' => 1, 'locale' => 'en', 'source_text' => 'One'],
        ['issue_id' => 2, 'locale' => 'en', 'source_text' => 'Two'],
        ['issue_id' => 3, 'locale' => 'en', 'source_text' => 'Three'],
        ['issue_id' => 4, 'locale' => 'en', 'source_text' => 'Four'],
        ['issue_id' => 5, 'locale' => 'en', 'source_text' => 'Five'],
    ];
    app()->instance(TranslationSyncRemoteClient::class, $fake);

    $result = app(RunTranslationSyncPipelineAction::class)->handle(1);

    expect($result)->toBe(['total' => 5, 'imported' => 5])
        ->and($fake->exportRuns)->toBe(1)
        ->and($fake->importRuns)->toBe(3);

    $status = app(TranslationSyncStatusStore::class)->read();
    expect($status['phase'] ?? null)->toBe(TranslationSyncPhase::Completed->value)
        ->and($status['imported'] ?? null)->toBe(5);
});

it('behoudt geimporteerde reeksen wanneer het vertalen halverwege crasht', function () {
    config(['translation_sync.batch_size' => 2]);

    $fake = new FakeTranslationSyncRemoteClient;
    $fake->exportItems = [
        ['issue_id' => 1, 'locale' => 'en', 'source_text' => 'One'],
        ['issue_id' => 2, 'locale' => 'en', 'source_text' => 'Two'],
        ['issue_id' => 3, 'locale' => 'en', 'source_text' => 'Three'],
        ['issue_id' => 4, 'locale' => 'en', 'source_text' => 'Four'],
    ];
    app()->instance(TranslationSyncRemoteClient::class, $fake);
    app()->instance(TranslationProviderInterface::class, new class implements TranslationProviderInterface
    {
        private int $calls = 0;

        public function translate(string $text, string $targetLanguage): string
        {
            $this->calls++;

            if ($this->calls > 2) {
                throw new RuntimeException('ollama timeout');
            }

            return '['.$targetLanguage.'] '.$text;
        }
    });

    expect(fn () => app(RunTranslationSyncPipelineAction::class)->handle(1))
        ->toThrow(RuntimeException::class, 'ollama timeout');

    expect($fake->importRuns)->toBe(1);

    $status = app(TranslationSyncStatusStore::class)->read();
    expect($status['phase'] ?? null)->toBe(TranslationSyncPhase::Failed->value)
        ->and($status['imported'] ?? null)->toBe(2);
});

it('slaat onvertaalbare exportregels over zonder de run te laten mislukken', function () {
    config(['translation_sync.batch_size' => 2]);

    $fake = new FakeTranslationSyncRemoteClient;
    $fake->exportItems = [
        ['issue_id' => 1, 'locale' => 'en', 'source_text' => 'One'],
        ['issue_id' => 2, 'locale' => 'en', 'source_text' => ''],
        ['issue_id' => 3, 'locale' => 'en', 'source_text' => 'Three'],
    ];
    app()->instance(TranslationSyncRemoteClient::class, $fake);

    $result = app(RunTranslationSyncPipelineAction::class)->handle(1);

    expect($result)->toBe(['total' => 3, 'imported' => 2]);

    $status = app(TranslationSyncStatusStore::class)->read();
    expect($status['phase'] ?? null)->toBe(TranslationSyncPhase::Completed->value)
        ->and($status['message'] ?? null)->toBe(__('platform.translation_sync.error_partial_translated', [
            'translated' => 2,
            'total' => 3,
        ]));
});

it('doorloopt de vertaal-sync pipeline met te lange locatienaam van provider', function () {
    $fake = new FakeTranslationSyncRemoteClient;
    $fake->exportItems = [
        [
            'location_id' => 5,
            'locale' => 'en',
            'source_name' => 'Hoofddepot',
        ],
    ];
    app()->instance(TranslationSyncRemoteClient::class, $fake);
    app()->instance(TranslationProviderInterface::class, new class implements TranslationProviderInterface
    {
        public function translate(string $text, string $targetLanguage): string
        {
            return 'Main depot '.str_repeat('x', 400);
        }
    });

    $result = app(RunTranslationSyncPipelineAction::class)->handle(1);

    expect($result)->toBe(['total' => 1, 'imported' => 1])
        ->and($fake->uploadedImportPath)->not->toBeNull();

    $payload = json_decode(file_get_contents($fake->uploadedImportPath), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['items'][0]['name'])->toHaveLength(255);
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
        ->and($result['slots_created'])->toBe(count(expectedTargetLocales('nl')))
        ->and(IssueTranslation::query()->where('issue_id', $issue->id)->count())->toBe(count(expectedTargetLocales('nl')));
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

    expect(app(CountPendingIssueTranslationsAction::class)->handle())->toBe(count(expectedTargetLocales('nl')));
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

it('detecteert vastgelopen status en kan resetten', function () {
    config(['translation_sync.stale_after_seconds' => 60]);

    $user = User::factory()->create(['is_superuser' => true, 'tenant_id' => null]);
    $store = app(TranslationSyncStatusStore::class);

    $store->write(TranslationSyncPhase::Uploading, (int) $user->id, [
        'total' => 5,
        'completed' => 5,
    ]);

    $status = $store->read();
    $status['updated_at'] = now()->subMinutes(5)->toIso8601String();
    \Illuminate\Support\Facades\Storage::disk('local')->put(
        (string) config('translation_sync.status_path'),
        json_encode($status, JSON_THROW_ON_ERROR),
    );

    Livewire::actingAs($user)
        ->test(PlatformTranslationSync::class)
        ->assertSee(__('platform.translation_sync.stalled'))
        ->call('resetStuck')
        ->assertSee(__('platform.translation_sync.reset_stuck_done'));

    expect($store->read())->toBeNull();
});

it('staat opnieuw starten toe wanneer status vastgelopen is', function () {
    Queue::fake();
    config(['translation_sync.stale_after_seconds' => 60]);

    $user = User::factory()->create(['is_superuser' => true, 'tenant_id' => null]);
    $store = app(TranslationSyncStatusStore::class);

    $store->write(TranslationSyncPhase::Uploading, (int) $user->id, [
        'total' => 2,
        'completed' => 2,
    ]);

    $status = $store->read();
    $status['updated_at'] = now()->subMinutes(5)->toIso8601String();
    \Illuminate\Support\Facades\Storage::disk('local')->put(
        (string) config('translation_sync.status_path'),
        json_encode($status, JSON_THROW_ON_ERROR),
    );

    app(StartTranslationSyncAction::class)->handle((int) $user->id);

    Queue::assertPushed(RunTranslationSyncJob::class);
    expect($store->read()['phase'] ?? null)->toBe(TranslationSyncPhase::Queued->value);
});

it('kan een lopende vertaal-run stoppen tijdens het vertalen', function () {
    $fake = new FakeTranslationSyncRemoteClient;
    $fake->exportItems = [
        ['issue_id' => 1, 'locale' => 'en', 'source_text' => 'One'],
        ['issue_id' => 2, 'locale' => 'en', 'source_text' => 'Two'],
        ['issue_id' => 3, 'locale' => 'en', 'source_text' => 'Three'],
    ];
    app()->instance(TranslationSyncRemoteClient::class, $fake);

    $user = User::factory()->create(['is_superuser' => true, 'tenant_id' => null]);
    $store = app(TranslationSyncStatusStore::class);

    TranslationSyncCancellation::request();

    $result = app(RunTranslationSyncPipelineAction::class)->handle((int) $user->id);

    expect($result)->toMatchArray(['cancelled' => true, 'imported' => 0])
        ->and($fake->importRuns)->toBe(0);

    $status = $store->read();
    expect($status['phase'] ?? null)->toBe(TranslationSyncPhase::Cancelled->value);
    expect(TranslationSyncCancellation::requested())->toBeFalse();
});

it('toont stop-knop en vraagt annulering aan via livewire', function () {
    $user = User::factory()->create(['is_superuser' => true, 'tenant_id' => null]);
    $store = app(TranslationSyncStatusStore::class);

    $store->write(TranslationSyncPhase::Translating, (int) $user->id, [
        'total' => 10,
        'completed' => 3,
    ]);

    Livewire::actingAs($user)
        ->test(PlatformTranslationSync::class)
        ->assertSee(__('platform.translation_sync.stop'))
        ->call('stop')
        ->assertSee(__('platform.translation_sync.stop_requested'));

    expect($store->read()['phase'] ?? null)->toBe(TranslationSyncPhase::Cancelling->value)
        ->and(TranslationSyncCancellation::requested())->toBeTrue();
});

it('meldt bij een gestopte run wat al op de server staat', function () {
    $user = User::factory()->create(['is_superuser' => true, 'tenant_id' => null]);

    app(TranslationSyncStatusStore::class)->write(TranslationSyncPhase::Cancelled, (int) $user->id, [
        'total' => 175,
        'imported' => 100,
        'message' => 'cancelled',
    ]);

    Livewire::actingAs($user)
        ->test(PlatformTranslationSync::class)
        ->assertSee(__('platform.translation_sync.completed_summary', ['imported' => 100, 'total' => 175]))
        ->assertSee(__('platform.translation_sync.partial_saved_note'))
        ->assertDontSee(__('platform.translation_sync.cancelled_note'));
});

it('weigert stoppen wanneer er geen actieve run is', function () {
    $user = User::factory()->create(['is_superuser' => true, 'tenant_id' => null]);

    expect(fn () => app(CancelTranslationSyncAction::class)->handle((int) $user->id))
        ->toThrow(RuntimeException::class, 'translation_sync_nothing_to_cancel');
});

it('zet cancelling na timeout om naar cancelled', function () {
    config(['translation_sync.cancelling_after_seconds' => 60]);

    $user = User::factory()->create(['is_superuser' => true, 'tenant_id' => null]);
    $store = app(TranslationSyncStatusStore::class);

    $store->write(TranslationSyncPhase::Cancelling, (int) $user->id, [
        'total' => 4,
        'completed' => 4,
        'message' => 'cancel_requested',
    ]);

    $status = $store->read();
    $status['updated_at'] = now()->subMinutes(2)->toIso8601String();
    \Illuminate\Support\Facades\Storage::disk('local')->put(
        (string) config('translation_sync.status_path'),
        json_encode($status, JSON_THROW_ON_ERROR),
    );

    TranslationSyncCancellation::request();

    $read = app(ReadTranslationSyncStatusAction::class)->handle();

    expect($read['phase'] ?? null)->toBe(TranslationSyncPhase::Cancelled->value)
        ->and(TranslationSyncCancellation::requested())->toBeFalse();
});

it('behoudt cancel-flag bij reset tijdens actieve run', function () {
    $user = User::factory()->create(['is_superuser' => true, 'tenant_id' => null]);
    $store = app(TranslationSyncStatusStore::class);

    $store->write(TranslationSyncPhase::Uploading, (int) $user->id, [
        'total' => 2,
        'completed' => 2,
    ]);
    TranslationSyncCancellation::request();

    app(ResetTranslationSyncStatusAction::class)->handle();

    expect($store->read())->toBeNull()
        ->and(TranslationSyncCancellation::requested())->toBeTrue();
});
