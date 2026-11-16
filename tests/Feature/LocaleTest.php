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
        ->and(\App\Support\Translation\LocaleSupport::dateInputLang('pt'))->toBe('pt-PT')
        ->and(\App\Support\Translation\LocaleSupport::dateInputLang('ro'))->toBe('ro-RO')
        ->and(\App\Support\Translation\LocaleSupport::dateInputLang('pl'))->toBe('pl-PL');
});

it('vertaalt de hulp van het portaal naar het roemeens', function () {
    app()->setLocale('ro');

    $help = \App\Support\PageHelp::for('portal.time');

    expect($help)->not->toBeNull()
        ->and($help['title'])->toBe('Ajutor — Portal Clock Point')
        ->and($help['actions'][0]['label'])->toBe('Nume și pictogramă')
        ->and(\App\Support\PageHelp::for('checkmate.portal.visit')['title'])->toBe('Ajutor — Vizită la client (lucrător)')
        ->and(__('page-help.button_label'))->toBe('Ajutor pentru această pagină');
});

it('vertaalt de hulp van het portaal naar het portugees', function () {
    app()->setLocale('pt');

    $help = \App\Support\PageHelp::for('portal.time');

    expect($help)->not->toBeNull()
        ->and($help['title'])->toBe('Ajuda — Portal Clock Point')
        ->and($help['actions'][0]['label'])->toBe('Nome e ícone')
        ->and(\App\Support\PageHelp::for('checkmate.portal.visit')['title'])->toBe('Ajuda — Visita ao cliente (trabalhador)')
        ->and(__('page-help.button_label'))->toBe('Ajuda desta página');
});

it('vertaalt de hulp van het portaal naar het pools', function () {
    app()->setLocale('pl');

    $help = \App\Support\PageHelp::for('portal.time');

    expect($help)->not->toBeNull()
        ->and($help['title'])->toBe('Pomoc — Portal Clock Point')
        ->and($help['actions'][0]['label'])->toBe('Imię i ikona')
        ->and(\App\Support\PageHelp::for('checkmate.portal.visit')['title'])->toBe('Pomoc — Wizyta u klienta (pracownik)')
        ->and(__('page-help.button_label'))->toBe('Pomoc do tej strony');
});

it('houdt portaal-talen buiten de backoffice en de Ollama-doeltalen', function () {
    $labels = array_merge(config('locales.labels'), config('locales.portal_labels'));
    $codes = array_keys($labels);

    expect(config('locales.supported'))->not->toContain('ro')
        ->and(config('locales.supported'))->not->toContain('pt')
        ->and(config('locales.supported'))->not->toContain('pl')
        ->and(config('locales.portal'))->toContain('ro')
        ->and(config('locales.portal'))->toContain('pt')
        ->and(config('locales.portal'))->toContain('pl')
        ->and(config('locales.portal_labels.pt'))->toBe('PO')
        ->and(config('locales.portal_labels.ro'))->toBe('RO')
        ->and(config('locales.portal_labels.pl'))->toBe('PL')
        ->and(array_search('pt', $codes, true))->toBeLessThan(array_search('ro', $codes, true))
        ->and(array_search('ro', $codes, true))->toBeLessThan(array_search('pl', $codes, true))
        ->and(\App\Support\Translation\LocaleSupport::targetLocalesForSource('nl'))->not->toContain('ro')
        ->and(\App\Support\Translation\LocaleSupport::targetLocalesForSource('nl'))->not->toContain('pt')
        ->and(\App\Support\Translation\LocaleSupport::targetLocalesForSource('nl'))->not->toContain('pl')
        ->and(__('portal.worker.sign_out', [], 'ro'))->toBe('Deconectare')
        ->and(__('time.portal.clock.in', [], 'ro'))->toBe('Pontaj intrare')
        ->and(__('portal.worker.sign_out', [], 'pt'))->toBe('Terminar sessão')
        ->and(__('time.portal.clock.in', [], 'pt'))->toBe('Registar entrada')
        ->and(__('portal.worker.sign_out', [], 'pl'))->toBe('Wyloguj się')
        ->and(__('time.portal.clock.in', [], 'pl'))->toBe('Zarejestruj wejście');
});
