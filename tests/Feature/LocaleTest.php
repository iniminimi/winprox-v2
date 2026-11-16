<?php

use App\Enums\TaskStatus;

it('laadt per-page JSON-vertalingen via de custom loader', function () {
    expect(__('common.status.new', [], 'nl'))->toBe('Nieuw (Open)')
        ->and(__('common.status.in_progress', [], 'fr'))->toBe('En cours')
        ->and(__('common.button.save', [], 'de'))->toBe('Speichern')
        ->and(__('common.button.save', [], 'es'))->toBe('Guardar')
        ->and(__('common.button.save', [], 'it'))->toBe('Salva');
});

it('koppelt elke taakstatus aan een vertaalsleutel', function () {
    foreach (TaskStatus::cases() as $status) {
        expect(__($status->labelKey(), [], 'nl'))->not->toBe($status->labelKey());
    }
});

it('mapt app-locale naar europese date-input locale', function () {
    expect(\App\Support\Translation\LocaleSupport::dateInputLang('nl'))->toBe('nl-NL')
        ->and(\App\Support\Translation\LocaleSupport::dateInputLang('en'))->toBe('en-GB')
        ->and(\App\Support\Translation\LocaleSupport::dateInputLang('fr'))->toBe('fr-FR')
        ->and(\App\Support\Translation\LocaleSupport::dateInputLang('ro'))->toBe('ro-RO');
});

it('houdt roemeens buiten de backoffice en de Ollama-doeltalen', function () {
    expect(config('locales.supported'))->not->toContain('ro')
        ->and(config('locales.portal'))->toContain('ro')
        ->and(\App\Support\Translation\LocaleSupport::targetLocalesForSource('nl'))->not->toContain('ro')
        ->and(__('portal.worker.sign_out', [], 'ro'))->toBe('Deconectare')
        ->and(__('time.portal.clock.in', [], 'ro'))->toBe('Pontaj intrare');
});
