<?php

use App\Enums\PromoLanding;
use App\Support\Marketing\SectorLandingVisuals;

it('levert vastgoed-foto’s wanneer de bestanden bestaan', function () {
    $visuals = SectorLandingVisuals::for(PromoLanding::RealEstate);

    expect($visuals)->toHaveKeys(['hero', 'problem', 'steps', 'places', 'roles', 'why', 'close'])
        ->and($visuals['hero'])->toBe('images/landing/general/welcome_01.jpg')
        ->and($visuals['close'])->toBe('images/landing/general/welcome_07.jpg')
        ->and(is_file(public_path($visuals['hero'])))->toBeTrue()
        ->and(is_file(public_path($visuals['close'])))->toBeTrue();
});

it('levert geen foto’s voor sectoren zonder assets', function () {
    expect(SectorLandingVisuals::for(PromoLanding::Hospitality))->toBe([]);
});
