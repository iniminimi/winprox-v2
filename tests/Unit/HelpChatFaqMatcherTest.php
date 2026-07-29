<?php

declare(strict_types=1);

use App\Actions\HelpChat\ProcessHelpChatMessageAction;
use App\Models\Tenant;
use App\Models\User;
use App\Support\HelpChat\HelpChatFaqMatcher;

beforeEach(function (): void {
    app()->setLocale('nl');
});

it('beantwoordt Time-vragen vanuit pagina-hulp Clock Points', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    $answer = $matcher->match('Hoe werkt Time / klokken inchecken?', 'nl');

    expect($answer)->toContain('Clock Point')
        ->and($answer)->not->toBe(__('faq.items.time_clock.summary'));
});

it('beantwoordt taak-toewijzing vanuit pagina-hulp i.p.v. korte FAQ', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    $answer = $matcher->match('hoe wijs ik een taak toe aan een team?', 'nl');

    expect($answer)->toContain('Team toewijzen')
        ->and($answer)->toContain('operationeel team')
        ->and($answer)->not->toBe(__('faq.items.team_follow_up.summary'));
});

it('beantwoordt reserveren vanuit pagina-hulp', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    expect($matcher->match('reserveren', 'nl'))->toContain('Reserveringen')
        ->and($matcher->match('Hoe werken reserveringen?', 'nl'))->toContain('Reserveringen')
        ->and($matcher->match('reserveren', 'nl'))->not->toBe(__('faq.items.reservations.summary'));
});

it('beantwoordt aan-de-slag vanuit handleiding', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    $answer = $matcher->match('aan de slag', 'nl');

    expect($answer)->toContain(__('manual.getting_started.title'))
        ->and($answer)->toContain(__('manual.step_1_title'));
});

it('beantwoordt pricing met IoT op Facility via FAQ', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    $answer = $matcher->match('Wat kost Facility?', 'nl');

    expect($answer)->toBe(__('faq.items.pricing.summary'))
        ->and($answer)->toContain('IoT Connect')
        ->and($answer)->toContain('Facility');
});

it('beantwoordt meldingsvragen vanuit pagina-hulp', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    $answer = $matcher->match('Hoe werkt een melding?', 'nl');

    expect($answer)->toContain('Meldingen')
        ->and($answer)->not->toBe(__('faq.items.how_it_works.summary'));
});

it('beantwoordt IoT vanuit pagina-hulp', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    $answer = $matcher->match('Wat is IoT Connect sensor?', 'nl');

    expect($answer)->toContain('IoT')
        ->and($answer)->not->toContain('Facility+')
        ->and($answer)->not->toBe(__('faq.items.iot.summary'));
});

it('koppelt voor-wie vragen aan FAQ', function (): void {
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
