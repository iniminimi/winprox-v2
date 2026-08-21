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

it('wijst domeinen zonder mailserver af via override', function () {
    config([
        'winprox.promo_email_mx_overrides' => [
            'dead.example' => false,
        ],
    ]);

    $result = app(AssessPromoCampaignEmailAction::class)->handle('info@dead.example');

    expect($result->accepted)->toBeFalse()
        ->and($result->reason)->toBe(PromoEmailPreflightReason::NoMx);
});

it('wijst URL-gecodeerde adressen af', function () {
    $result = app(AssessPromoCampaignEmailAction::class)->handle('%20infocasaverata@gmail.com');

    expect($result->accepted)->toBeFalse()
        ->and($result->reason)->toBe(PromoEmailPreflightReason::InvalidSyntax);
});

it('wijst hotelgids-subdomeinen af', function () {
    config([
        'winprox.promo_email_mx_overrides' => [
            'granadahotels.org' => true,
            'occidental-granada.granadahotels.org' => true,
        ],
    ]);

    $result = app(AssessPromoCampaignEmailAction::class)->handle(
        'info@occidental-granada.granadahotels.org',
    );

    expect($result->accepted)->toBeFalse()
        ->and($result->reason)->toBe(PromoEmailPreflightReason::ListingSubdomain);
});

it('accepteert een geldig adres met MX-override', function () {
    config([
        'winprox.promo_email_mx_overrides' => [
            'alive.example' => true,
        ],
    ]);

    $result = app(AssessPromoCampaignEmailAction::class)->handle('Info@Alive.example');

    expect($result->accepted)->toBeTrue()
        ->and($result->normalizedEmail)->toBe('info@alive.example')
        ->and($result->reason)->toBeNull();
});
