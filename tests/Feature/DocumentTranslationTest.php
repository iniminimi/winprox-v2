<?php

use App\Actions\Communication\EnsureDocumentTranslationSlotsAction;
use App\Actions\Communication\ExportPendingDocumentTranslationsAction;
use App\Actions\Communication\ImportDocumentTranslationsAction;
use App\Actions\Communication\TranslateDocumentAction;
use App\Actions\Locations\ToggleLocationDocumentActiveAction;
use App\Actions\Locations\UpdateLocationDocumentAction;
use App\Enums\DocumentTranslationStatus;
use App\Livewire\Locations\Documents;
use App\Models\Document;
use App\Models\DocumentTranslation;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Tenancy;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\FakeTranslationProvider;

afterEach(fn () => Tenancy::forget());

beforeEach(function () {
    app()->instance(TranslationProviderInterface::class, new FakeTranslationProvider);
});

it('seed pending vertaalrijen na aanmaken actief document', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Veiligheidsblad compressor',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureDocumentTranslationSlotsAction::class)->handle($document);

    $rows = DocumentTranslation::query()->where('document_id', $document->id)->get();

    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('locale')->sort()->values()->all())->toBe(['de', 'en', 'fr'])
        ->and($rows->every(fn ($row) => $row->status === DocumentTranslationStatus::Pending))->toBeTrue();
});

it('maakt geen vertaalrijen voor inactief document', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Concept',
        'original_language' => 'nl',
        'is_active' => false,
    ]);

    app(EnsureDocumentTranslationSlotsAction::class)->handle($document);

    expect(DocumentTranslation::query()->where('document_id', $document->id)->count())->toBe(0);
});

it('seed vertaalrijen bij activeren', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Handleiding machine',
        'original_language' => 'nl',
        'is_active' => false,
        'published_at' => null,
    ]);

    app(ToggleLocationDocumentActiveAction::class)->handle($document);

    expect(DocumentTranslation::query()->where('document_id', $document->id)->count())->toBe(3);
});

it('vertaalt document via de provider', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Safety sheet compressor',
        'original_language' => 'en',
        'is_active' => true,
    ]);

    app(EnsureDocumentTranslationSlotsAction::class)->handle($document);

    $row = app(TranslateDocumentAction::class)->handle($document, 'nl', $user->id);

    expect($row->status)->toBe(DocumentTranslationStatus::Completed)
        ->and($row->description)->toBe('[nl] Safety sheet compressor')
        ->and($document->fresh()->localizedDescription('nl'))->toBe('[nl] Safety sheet compressor');
});

it('exporteert en importeert pending documentvertalingen', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Veiligheidsblad',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureDocumentTranslationSlotsAction::class)->handle($document);

    $exportItems = app(ExportPendingDocumentTranslationsAction::class)->handle();

    expect($exportItems)->toHaveCount(3)
        ->and($exportItems[0])->toHaveKeys(['document_id', 'source_text', 'locale']);

    $imported = app(ImportDocumentTranslationsAction::class)->handle([
        [
            'document_id' => $document->id,
            'locale' => 'en',
            'description' => 'Safety sheet',
        ],
    ]);

    expect($imported)->toBe(1)
        ->and($document->fresh()->localizedDescription('en'))->toBe('Safety sheet');
});

it('invalideert vertalingen bij wijziging documentomschrijving', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Veiligheidsblad',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureDocumentTranslationSlotsAction::class)->handle($document);
    app(TranslateDocumentAction::class)->handle($document, 'en', $user->id);

    app(UpdateLocationDocumentAction::class)->handle(
        $location,
        $document,
        [
            'description' => 'Nieuw veiligheidsblad',
            'unit_id' => null,
            'is_public' => true,
            'requires_verification' => false,
            'is_active' => true,
        ],
        null,
        $tenant->id,
        $user->id,
    );

    $english = DocumentTranslation::query()
        ->where('document_id', $document->id)
        ->where('locale', 'en')
        ->first();

    expect($english->status)->toBe(DocumentTranslationStatus::Pending)
        ->and($english->description)->toBeNull();
});

it('weigert vertaling voor inactief document', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Draft',
        'original_language' => 'nl',
        'is_active' => false,
    ]);

    app(TranslateDocumentAction::class)->handle($document, 'en');
})->throws(ValidationException::class);

it('toont vertaal-preview in document-bewerk-modal', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'en']);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Veiligheidsblad',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureDocumentTranslationSlotsAction::class)->handle($document);
    app(TranslateDocumentAction::class)->handle($document, 'en');

    Livewire::actingAs($user)
        ->test(Documents::class, ['location' => $location])
        ->call('openEditModal', $document->id)
        ->assertSet('previewLocale', 'en')
        ->assertSee('[en] Veiligheidsblad');
});

it('toont vertaalde documentomschrijving in beheerlijst volgens gebruikerstaal', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'en']);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Veiligheidsblad',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureDocumentTranslationSlotsAction::class)->handle($document);
    app(ImportDocumentTranslationsAction::class)->handle([
        [
            'document_id' => $document->id,
            'locale' => 'en',
            'description' => 'Safety sheet',
        ],
    ]);

    Livewire::actingAs($user)
        ->test(Documents::class, ['location' => $location])
        ->assertSee('Safety sheet');
});
