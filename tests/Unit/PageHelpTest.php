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
        ->and(PageHelp::for('esg.point'))->not->toBeNull()
        ->and(PageHelp::for('esg.point')['title'])->toBe('Hulp — Meetpunthistoriek')
        ->and(PageHelp::for('esg.measurements'))->not->toBeNull()
        ->and(PageHelp::for('esg.measurements')['title'])->toBe('Hulp — ESG-metingenoverzicht')
        ->and(PageHelp::for('esg.dashboard'))->not->toBeNull()
        ->and(PageHelp::for('esg.dashboard')['title'])->toBe('Hulp — ESG-dashboard');
});

it('laadt paginahulp voor Time-schermen', function (): void {
    app()->setLocale('nl');

    expect(PageHelp::for('time.presence'))->not->toBeNull()
        ->and(PageHelp::for('time.presence')['title'])->toBe('Hulp — Aanwezigheid')
        ->and(PageHelp::for('time.alarms'))->not->toBeNull()
        ->and(PageHelp::for('time.alarms')['title'])->toBe('Hulp — Alarmen')
        ->and(PageHelp::for('time.shifts'))->not->toBeNull()
        ->and(PageHelp::for('time.clock_points'))->not->toBeNull()
        ->and(PageHelp::for('portal.time'))->not->toBeNull()
        ->and(PageHelp::for('portal.time')['title'])->toBe('Hulp — Clock Point portaal');
});

it('laadt paginahulp voor reserveringen', function (): void {
    app()->setLocale('nl');

    $help = PageHelp::for('reservations');

    expect($help)->not->toBeNull()
        ->and($help['title'])->toBe('Hulp — Reserveringen')
        ->and($help['actions'])->not->toBeEmpty()
        ->and(collect($help['statuses'])->pluck('key')->all())->toContain('pending');
});

it('laadt paginahulp voor e-mail uitschrijvingen', function (): void {
    app()->setLocale('nl');

    $help = PageHelp::for('platform.email_unsubscribes');

    expect($help)->not->toBeNull()
        ->and($help['title'])->toBe('Hulp — E-mail uitschrijvingen')
        ->and($help['actions'])->not->toBeEmpty();
});

it('laadt paginahulp voor platformaudit', function (): void {
    app()->setLocale('nl');

    $help = PageHelp::for('platform.audit');

    expect($help)->not->toBeNull()
        ->and($help['title'])->toBe('Hulp — Activiteitenlog')
        ->and($help['actions'])->not->toBeEmpty();
});
