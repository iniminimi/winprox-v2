<?php

declare(strict_types=1);

use App\Actions\HelpChat\ProcessHelpChatMessageAction;
use App\Models\Tenant;
use App\Models\User;
use App\Support\HelpChat\HelpChatFaqMatcher;

beforeEach(function (): void {
    app()->setLocale('nl');
});

it('beantwoordt Time-vragen zonder QR-hijack via klok', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    $answer = $matcher->match('Hoe werkt Time / klokken inchecken?', 'nl');

    expect($answer)->toBe(__('faq.items.time_clock.summary'))
        ->and($answer)->toContain('Time');
});

it('beantwoordt reserveringsvragen', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    expect($matcher->match('Hoe werken reserveringen?', 'nl'))
        ->toBe(__('faq.items.reservations.summary'));

    expect($matcher->match('reserveren', 'nl'))
        ->toBe(__('faq.items.reservations.summary'))
        ->and($matcher->match('reserveren', 'nl'))->toContain('Reserveringen');
});

it('beantwoordt pricing met IoT op Facility', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    $answer = $matcher->match('Wat kost Facility abonnement?', 'nl');

    expect($answer)->toBe(__('faq.items.pricing.summary'))
        ->and($answer)->toContain('IoT Connect')
        ->and($answer)->toContain('Facility');
});

it('koppelt how-it-works summary zonder optionele Time', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    $answer = $matcher->match('Hoe werkt een melding?', 'nl');

    expect($answer)->toBe(__('faq.items.how_it_works.summary'))
        ->and($answer)->toContain('Time standaard')
        ->and($answer)->not->toContain('Optioneel: Time');
});

it('koppelt IoT zonder Facility+ shorthand', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    $answer = $matcher->match('Wat is IoT Connect sensor?', 'nl');

    expect($answer)->toBe(__('faq.items.iot.summary'))
        ->and($answer)->toContain('Facility en Corporate')
        ->and($answer)->not->toContain('Facility+');
});

it('koppelt voor-wie vragen', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    expect($matcher->match('Voor wie is WinProx?', 'nl'))
        ->toBe(__('faq.items.for_who.summary'));
});

it('koppelt pagina-hulp zonder bare status-woord', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    expect($matcher->match('Waar vind ik pagina-hulp?', 'nl'))
        ->toBe(__('faq.items.page_help.summary'));
});

it('geeft tenant-insight bij overzicht, niet bij bare status', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $action = app(ProcessHelpChatMessageAction::class);

    $insight = $action->handle($user, 'Geef een overzicht');
    expect($insight['content'])->toBe(
        __('help.insight.summary', [
            'locations' => 0,
            'units' => 0,
            'issues' => 0,
            'tasks' => 0,
        ])
    );

    $matcher = app(HelpChatFaqMatcher::class);
    expect($matcher->match('status', 'nl'))->toBeNull();
});
