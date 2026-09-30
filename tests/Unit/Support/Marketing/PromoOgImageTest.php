<?php

declare(strict_types=1);

use App\Enums\PromoLanding;
use App\Support\Marketing\PromoOgImage;

it('gebruikt og_1 voor site en og_2 voor portaal', function (): void {
    $og2Path = public_path('images/promo/og_2.jpg');
    if (! is_file($og2Path) && is_file(public_path('images/promo/og_1.jpg'))) {
        copy(public_path('images/promo/og_1.jpg'), $og2Path);
    }

    $site = PromoOgImage::forSite();
    $portal = PromoOgImage::forPortal();

    expect($site)->toHaveKeys(['url', 'width', 'height', 'type'])
        ->and($site['url'])->toContain('/images/promo/og_1.jpg')
        ->and($portal['url'])->toContain('/images/promo/og_2.jpg')
        ->and($portal['type'])->toBe('image/jpeg');
});

it('gebruikt og_2 voor de checkmate-landing en og_1 voor de rest', function (): void {
    $og2Path = public_path('images/promo/og_2.jpg');
    if (! is_file($og2Path) && is_file(public_path('images/promo/og_1.jpg'))) {
        copy(public_path('images/promo/og_1.jpg'), $og2Path);
    }

    expect(PromoOgImage::forLanding(PromoLanding::Checkmate)['url'])
        ->toContain('/images/promo/og_2.jpg')
        ->and(PromoOgImage::forLanding(PromoLanding::Prikklok)['url'])
        ->toContain('/images/promo/og_1.jpg')
        ->and(PromoOgImage::forLanding(PromoLanding::Government)['url'])
        ->toContain('/images/promo/og_1.jpg');
});
