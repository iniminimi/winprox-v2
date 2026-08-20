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

it('beantwoordt starttemplate vanuit dashboard-paginahulp', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    expect($matcher->match('starttemplate', 'nl'))->toContain('Starttemplate')
        ->and($matcher->match('op weg geholpen', 'nl'))->toContain('Wil je op weg geholpen worden?');
});

it('beantwoordt pricing via FAQ met nieuwe tier-structuur', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    $answer = $matcher->match('Wat kost Facility?', 'nl');

    expect($answer)->toBe(__('faq.items.pricing.summary'))
        ->and($answer)->toContain('10/50/100')
        ->and($answer)->toContain('Corporate');
});

it('beantwoordt meldingsvragen vanuit pagina-hulp', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    $answer = $matcher->match('Hoe werkt een melding?', 'nl');

    expect($answer)->toContain('Meldingen')
        ->and($answer)->not->toBe(__('faq.items.how_it_works.summary'));
});

it('beantwoordt wat is een unit check vanuit pagina-hulp unit checks', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    $answer = $matcher->match('wat is een unit check?', 'nl');

    expect($answer)->toContain('Wat is een unit check?')
        ->and($answer)->toContain('OK of Niet OK')
        ->and($answer)->toContain('Inschakelen')
        ->and($answer)->not->toContain('QR-afdrukblad')
        ->and($answer)->not->toContain('Avery');
});

it('beantwoordt units-vragen vanuit pagina-hulp Units, niet locatie-QR', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    $answer = $matcher->match('Waar vind ik de units?', 'nl');

    expect($answer)->toContain('Overzicht')
        ->and($answer)->toContain('Filter')
        ->and($answer)->not->toContain('QR-afdrukblad')
        ->and($answer)->not->toContain('Avery');
});

it('beantwoordt locaties-vragen vanuit pagina-hulp Locaties', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    $answer = $matcher->match('Hoe voeg ik locaties toe?', 'nl');

    expect($answer)->toContain('Locatie toevoegen')
        ->and($answer)->not->toContain('QR-afdrukblad');
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

it('beantwoordt Microsoft-login vanuit pagina-hulp backoffice', function (): void {
    $matcher = app(HelpChatFaqMatcher::class);

    $answer = $matcher->match('inloggen met microsoft', 'nl');

    expect($answer)->toContain('Inloggen met Microsoft')
        ->and($answer)->toContain('Clock Point');
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
