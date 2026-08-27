<?php

use App\Actions\Communication\InvalidateUnusableContentTranslationsAction;
use App\Actions\Communication\TranslateUnitAction;
use App\Enums\UnitTranslationStatus;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitTranslation;
use App\Services\Translation\OllamaProvider;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Tenancy;
use App\Support\Translation\TranslationOutputGuard;
use Illuminate\Support\Facades\Http;

afterEach(fn () => Tenancy::forget());

it('detecteert Ollama meta-antwoorden als onbruikbaar', function () {
    expect(TranslationOutputGuard::isUnusable(
        "I don't see any text to translate. Please provide the text you'd like me to translate.",
        'Kamer 12',
    ))->toBeTrue()
        ->and(TranslationOutputGuard::isUnusable('Room 12', 'Kamer 12'))->toBeFalse()
        ->and(TranslationOutputGuard::isUnusable('', 'Kamer 12'))->toBeTrue();
});

it('toont bronnaam in portal-locale wanneer unitvertaling meta-tekst is', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Kamer 12',
        'description' => 'Nabij lift',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    UnitTranslation::query()->create([
        'unit_id' => $unit->id,
        'locale' => 'en',
        'status' => UnitTranslationStatus::Completed,
        'name' => "I don't see any text to translate. Please provide the text you'd like me to translate.",
        'description' => 'Near the elevator',
    ]);

    expect($unit->fresh()->localizedName('en'))->toBe('Kamer 12')
        ->and($unit->fresh()->localizedDescription('en'))->toBe('Near the elevator');
});

it('zet onbruikbare completed unitvertalingen terug op pending', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Kamer 12',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    $row = UnitTranslation::query()->create([
        'unit_id' => $unit->id,
        'locale' => 'en',
        'status' => UnitTranslationStatus::Completed,
        'name' => "I don't see any text to translate. Please provide the text you'd like me to translate.",
        'description' => null,
    ]);

    $result = app(InvalidateUnusableContentTranslationsAction::class)->handle();

    expect($result['invalidated'])->toBeGreaterThanOrEqual(1)
        ->and($row->fresh()->status)->toBe(UnitTranslationStatus::Pending)
        ->and($row->fresh()->name)->toBeNull();
});

it('weigert meta-output van OllamaProvider en geeft lege string terug', function () {
    Http::fake([
        '*/api/generate' => Http::response([
            'response' => "I don't see any text to translate. Please provide the text you'd like me to translate.",
        ], 200),
    ]);

    config(['ollama.enabled' => true, 'ollama.url' => 'http://ollama.test']);

    $out = app(OllamaProvider::class)->translate('Kamer 12', 'en', 'nl');

    expect($out)->toBe('');
});

it('markeert unitvertaling als failed bij meta-output van de provider', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Kamer 12',
        'description' => '',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    UnitTranslation::query()->create([
        'unit_id' => $unit->id,
        'locale' => 'en',
        'status' => UnitTranslationStatus::Pending,
        'name' => null,
        'description' => null,
    ]);

    app()->instance(TranslationProviderInterface::class, new class implements TranslationProviderInterface
    {
        public function translate(string $text, string $targetLanguage, ?string $sourceLanguage = null): string
        {
            return "I don't see any text to translate. Please provide the text you'd like me to translate.";
        }
    });

    $row = app(TranslateUnitAction::class)->handle($unit, 'en');

    expect($row->status)->toBe(UnitTranslationStatus::Failed)
        ->and($row->name)->toBeNull();
});
