<?php

use App\Actions\Communication\EnsureDocumentTranslationSlotsAction;
use App\Actions\Communication\EnsureTaskTranslationSlotsAction;
use App\Models\Document;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\UnitCheckList;
use App\Models\UnitCheckListTranslation;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

afterEach(fn () => Tenancy::forget());

it('exporteert pending taak- en documentvertalingen via translation:export', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $task = Task::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Vervang pakking',
        'original_language' => 'nl',
    ]);

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Veiligheidsblad',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureTaskTranslationSlotsAction::class)->handle($task);
    app(EnsureDocumentTranslationSlotsAction::class)->handle($document);

    $path = storage_path('app/exports/translations.json');

    Artisan::call('translation:export');

    $payload = json_decode(File::get($path), true);

    expect($payload['items'] ?? [])->not->toBeEmpty();

    $taskIds = collect($payload['items'])->pluck('task_id')->filter()->all();
    $documentIds = collect($payload['items'])->pluck('document_id')->filter()->all();

    expect($taskIds)->toContain($task->id)
        ->and($documentIds)->toContain($document->id);
});

it('backfillt en exporteert pending checklist-vertalingen via translation:export', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $list = UnitCheckList::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Schoonmaak checklist',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    expect(UnitCheckListTranslation::query()->where('unit_check_list_id', $list->id)->count())->toBe(0);

    Artisan::call('translation:export');

    $payload = json_decode(File::get(storage_path('app/exports/translations.json')), true);
    $listIds = collect($payload['items'] ?? [])->pluck('unit_check_list_id')->filter()->unique()->all();

    expect(UnitCheckListTranslation::query()->where('unit_check_list_id', $list->id)->count())->toBeGreaterThan(0)
        ->and($listIds)->toContain($list->id);
});
