<?php

use App\Enums\PromoLanding;
use App\Support\Marketing\SectorLandingVideo;

it('vindt de industry-promo-video voor nl', function () {
    $path = SectorLandingVideo::relativePath(PromoLanding::Industry, 'nl');

    expect($path)->toBe('video/nl/industry_promo_nl.mp4')
        ->and(is_file(public_path($path)))->toBeTrue();
});

it('geeft null wanneer er geen industry-video voor die taal is', function () {
    expect(SectorLandingVideo::relativePath(PromoLanding::Industry, 'xx'))->toBeNull();
});
