<?php

declare(strict_types=1);

use App\Support\PageHelp;

it('laadt paginahulp voor een bekende pagina', function (): void {
    app()->setLocale('nl');

    $help = PageHelp::for('issues.list');

    expect($help)->not->toBeNull()
        ->and($help['title'])->toBe('Hulp — Meldingen')
        ->and($help['actions'])->not->toBeEmpty()
        ->and($help['statuses'])->toHaveCount(4);
});

it('geeft null voor een onbekende paginasleutel', function (): void {
    expect(PageHelp::for('does.not.exist'))->toBeNull();
});

it('ondersteunt geneste acties in paginahulp', function (): void {
    app()->setLocale('nl');

    $help = PageHelp::for('settings');

    expect($help)->not->toBeNull();

    $nested = collect($help['actions'])->first(fn (array $a): bool => $a['label'] === 'Tekst');

    expect($nested)->not->toBeNull()
        ->and($nested['nested'])->toBeTrue();
});

it('laadt paginahulp voor ESG-schermen', function (): void {
    app()->setLocale('nl');

    expect(PageHelp::for('esg.indicators'))->not->toBeNull()
        ->and(PageHelp::for('esg.indicators')['title'])->toBe('Hulp — ESG-metingen')
        ->and(PageHelp::for('esg.measurements'))->not->toBeNull()
        ->and(PageHelp::for('esg.measurements')['title'])->toBe('Hulp — ESG-metingenoverzicht');
});

it('laadt paginahulp voor Time-schermen', function (): void {
    app()->setLocale('nl');

    expect(PageHelp::for('time.presence'))->not->toBeNull()
        ->and(PageHelp::for('time.presence')['title'])->toBe('Hulp — Aanwezigheid')
        ->and(PageHelp::for('time.shifts'))->not->toBeNull()
        ->and(PageHelp::for('time.clock_points'))->not->toBeNull();
});
