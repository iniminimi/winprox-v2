<?php

declare(strict_types=1);

use App\Support\Marketing\PromoEmailMxLookup;

it('weigert domeinen zonder punt of met IP', function () {
    $lookup = new PromoEmailMxLookup;

    expect($lookup->domainAcceptsMail('localhost'))->toBeFalse()
        ->and($lookup->domainAcceptsMail('127.0.0.1'))->toBeFalse()
        ->and($lookup->domainAcceptsMail(''))->toBeFalse();
});

it('gebruikt overrides vóór DNS', function () {
    config([
        'winprox.promo_email_preflight_dns' => true,
        'winprox.promo_email_mx_overrides' => [
            'dead.test' => false,
            'alive.test' => true,
        ],
    ]);

    $lookup = new PromoEmailMxLookup;

    expect($lookup->domainAcceptsMail('dead.test'))->toBeFalse()
        ->and($lookup->domainAcceptsMail('alive.test'))->toBeTrue();
});

it('slaat DNS over wanneer preflight-dns uit staat', function () {
    config([
        'winprox.promo_email_preflight_dns' => false,
        'winprox.promo_email_mx_overrides' => [],
    ]);

    $lookup = new PromoEmailMxLookup;

    expect($lookup->domainAcceptsMail('anything-without-dns.test'))->toBeTrue();
});

it('weigert gereserveerd .invalid TLD zonder mailserver', function () {
    config([
        'winprox.promo_email_preflight_dns' => true,
        'winprox.promo_email_mx_overrides' => [],
    ]);

    $lookup = new PromoEmailMxLookup;

    expect($lookup->domainAcceptsMail('no-mail.invalid'))->toBeFalse();
});
