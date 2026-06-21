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
