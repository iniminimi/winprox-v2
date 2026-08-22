<?php

declare(strict_types=1);

use App\Actions\Marketing\AssessPromoCampaignEmailAction;
use App\Enums\EmailUnsubscribeSource;
use App\Enums\PromoEmailPreflightReason;
use App\Models\EmailUnsubscribe;

it('accepteert een leeg adres niet als e-mail maar wijst het niet af', function () {
    $result = app(AssessPromoCampaignEmailAction::class)->handle('  ');

    expect($result->hasEmail)->toBeFalse()
        ->and($result->accepted)->toBeFalse()
        ->and($result->reason)->toBeNull();
});

it('wijst ongeldige syntax af', function () {
    $result = app(AssessPromoCampaignEmailAction::class)->handle('niet-een-adres');

    expect($result->accepted)->toBeFalse()
        ->and($result->reason)->toBe(PromoEmailPreflightReason::InvalidSyntax);
});

it('wijst eerder gebouncete adressen af', function () {
    EmailUnsubscribe::query()->create([
        'email' => 'bounce@example.com',
        'source' => EmailUnsubscribeSource::Undeliverable,
        'unsubscribed_at' => now(),
    ]);

    $result = app(AssessPromoCampaignEmailAction::class)->handle('Bounce@Example.com');

    expect($result->accepted)->toBeFalse()
        ->and($result->reason)->toBe(PromoEmailPreflightReason::PreviouslyBounced)
        ->and($result->normalizedEmail)->toBe('bounce@example.com');
});

it('wijst uitgeschreven adressen af', function () {
    EmailUnsubscribe::query()->create([
        'email' => 'unsub@example.com',
        'source' => EmailUnsubscribeSource::Voluntary,
        'unsubscribed_at' => now(),
    ]);

    $result = app(AssessPromoCampaignEmailAction::class)->handle('unsub@example.com');

    expect($result->accepted)->toBeFalse()
        ->and($result->reason)->toBe(PromoEmailPreflightReason::Unsubscribed);
});

it('wijst URL-gecodeerde rommel af die niet tot een adres te herstellen is', function () {
    $result = app(AssessPromoCampaignEmailAction::class)->handle(
        '47.55555%25252525252c+-122.55555@shein.shop',
    );

    expect($result->accepted)->toBeFalse()
        ->and($result->reason)->toBe(PromoEmailPreflightReason::InvalidSyntax);
});

it('maakt %20- en //-prefix schoon tot een geldig adres', function () {
    $fromEncoded = app(AssessPromoCampaignEmailAction::class)->handle('%20Info@Alive.example');
    $fromSlash = app(AssessPromoCampaignEmailAction::class)->handle('//info@alive.example');

    expect($fromEncoded->accepted)->toBeTrue()
        ->and($fromEncoded->normalizedEmail)->toBe('info@alive.example')
        ->and($fromSlash->accepted)->toBeTrue()
        ->and($fromSlash->normalizedEmail)->toBe('info@alive.example');
});

it('accepteert een syntactisch geldig adres zonder DNS-check', function () {
    $result = app(AssessPromoCampaignEmailAction::class)->handle('Info@Alive.example');

    expect($result->accepted)->toBeTrue()
        ->and($result->normalizedEmail)->toBe('info@alive.example')
        ->and($result->reason)->toBeNull();
});
