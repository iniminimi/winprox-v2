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
