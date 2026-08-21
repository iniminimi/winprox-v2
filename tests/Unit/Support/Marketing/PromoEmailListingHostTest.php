<?php

declare(strict_types=1);

use App\Support\Marketing\PromoEmailListingHost;

it('herkent hotelgids-subdomeinen uit URL-slugs', function (string $domain) {
    expect(PromoEmailListingHost::looksLikeDirectoryListing($domain))->toBeTrue();
})->with([
    'occidental-granada.granadahotels.org',
    'ayz-villegas-auto-check-in-property.madridhotels.it',
    'achotelalcaldehenaresbymarriott.com-madrid.com',
    'ac-hotel-ciudad-de-sevilla.sevillehotels.net',
    'grupotel-acapulco-playa-playa-de-palma.hotels-of-mallorca.com',
    'pensionacibeche.hotelsantiagodecompostela.net',
    'hotel-boutique-spa-adealba.webhotel.com.es',
    'apartamento-edificio-agata.hotelsinbenalmadena.com',
    'sensimar-aguait-resort-spa-cala-ratjada.hotelescalaratjada.com',
]);

it('laat echte maildomeinen zonder gids-subdomein door', function (string $domain) {
    expect(PromoEmailListingHost::looksLikeDirectoryListing($domain))->toBeFalse();
})->with([
    'hotel-caspel.com',
    'hotmail.com',
    'gmail.com',
    'melia.com',
    'ibis.accor.com',
    'parador.es',
]);
