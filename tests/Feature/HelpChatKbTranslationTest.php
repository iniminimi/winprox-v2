<?php

declare(strict_types=1);

use App\Actions\Communication\ExportPendingHelpChatKbEntryTranslationsAction;
use App\Actions\Communication\ImportHelpChatKbEntryTranslationsAction;
use App\Actions\HelpChat\SaveHelpChatKbEntryAction;
use App\Enums\HelpChatKnowledgeBaseEntryTranslationStatus;
use App\Models\HelpChatKnowledgeBaseEntry;
use App\Models\HelpChatKnowledgeBaseEntryTranslation;
use App\Support\HelpChat\HelpChatFaqMatcher;
use App\Services\Translation\TranslationProviderInterface;
use Tests\Support\FakeTranslationProvider;

beforeEach(function (): void {
    app()->instance(TranslationProviderInterface::class, new FakeTranslationProvider);
});

it('maakt pending vertaalrijen aan na opslaan kennisbank-item', function (): void {
    $entry = app(SaveHelpChatKbEntryAction::class)->handle(
        null,
        'nl',
        'export_taken',
        ['exporteer taken'],
        'Ga naar Taken en klik Download rapport.',
        true,
    );

    $rows = HelpChatKnowledgeBaseEntryTranslation::query()
        ->where('help_chat_knowledge_base_entry_id', $entry->id)
        ->get();

    expect($rows)->toHaveCount(count(expectedTargetLocales('nl')))
        ->and($rows->every(fn ($row) => $row->status === HelpChatKnowledgeBaseEntryTranslationStatus::Pending))->toBeTrue();
});

it('exporteert en importeert pending kennisbankvertalingen', function (): void {
    $entry = app(SaveHelpChatKbEntryAction::class)->handle(
        null,
        'nl',
        'export_taken',
        ['exporteer taken'],
        'Ga naar Taken en klik Download rapport.',
        true,
    );

    $exportItems = app(ExportPendingHelpChatKbEntryTranslationsAction::class)->handle();

    expect($exportItems)->toHaveCount(count(expectedTargetLocales('nl')))
        ->and($exportItems[0])->toHaveKeys(['help_chat_kb_entry_id', 'source_answer', 'source_patterns', 'locale']);

    $imported = app(ImportHelpChatKbEntryTranslationsAction::class)->handle([
        [
            'help_chat_kb_entry_id' => $entry->id,
            'locale' => 'en',
            'answer' => 'Go to Tasks and click Download report.',
            'patterns' => ['export tasks'],
        ],
    ]);

    expect($imported)->toBe(1);

    $matcher = app(HelpChatFaqMatcher::class);
    app()->setLocale('en');

    expect($matcher->match('how do I export tasks', 'en'))
        ->toBe('Go to Tasks and click Download report.');
});

it('geeft brontaal-antwoord voor gebruikers in de brontaal', function (): void {
    app(SaveHelpChatKbEntryAction::class)->handle(
        null,
        'nl',
        'qzxw_kb_brontaal_test',
        ['qzxwplkmn export taken kb test'],
        'Ga naar Taken en klik Download rapport.',
        true,
    );

    $matcher = app(HelpChatFaqMatcher::class);

    expect($matcher->match('qzxwplkmn export taken kb test', 'nl'))
        ->toBe('Ga naar Taken en klik Download rapport.');
});

it('zet vertalingen opnieuw pending bij wijziging bronantwoord', function (): void {
    $entry = app(SaveHelpChatKbEntryAction::class)->handle(
        null,
        'nl',
        'export_taken',
        ['exporteer taken'],
        'Ga naar Taken en klik Download rapport.',
        true,
    );

    app(ImportHelpChatKbEntryTranslationsAction::class)->handle([
        [
            'help_chat_kb_entry_id' => $entry->id,
            'locale' => 'en',
            'answer' => 'Go to Tasks and click Download report.',
            'patterns' => ['export tasks'],
        ],
    ]);

    app(SaveHelpChatKbEntryAction::class)->handle(
        $entry->id,
        'nl',
        'export_taken',
        ['exporteer taken'],
        'Nieuw antwoord in het Nederlands.',
        true,
    );

    $row = HelpChatKnowledgeBaseEntryTranslation::query()
        ->where('help_chat_knowledge_base_entry_id', $entry->id)
        ->where('locale', 'en')
        ->first();

    expect($row?->status)->toBe(HelpChatKnowledgeBaseEntryTranslationStatus::Pending)
        ->and($row?->answer)->toBeNull();
});
