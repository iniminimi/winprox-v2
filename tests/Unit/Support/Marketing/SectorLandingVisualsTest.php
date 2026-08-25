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
    expect(SectorLandingVisuals::for(PromoLanding::Healthcare))->toBe([]);
});

it('levert hospitality-foto’s wanneer de bestanden bestaan', function () {
    $visuals = SectorLandingVisuals::for(PromoLanding::Hospitality);

    expect($visuals)->toHaveKeys(['hero', 'problem', 'steps', 'places', 'roles', 'close'])
        ->and($visuals)->not->toHaveKey('why')
        ->and($visuals['hero'])->toBe('images/landing/hospitality/image_03.jpg')
        ->and($visuals['close'])->toBe('images/landing/hospitality/image_06.jpg')
        ->and(SectorLandingVisuals::modifiers(PromoLanding::Hospitality))
        ->toHaveKey('steps')
        ->and(SectorLandingVisuals::layouts(PromoLanding::Hospitality))
        ->toHaveKey('places')
        ->and(SectorLandingVisuals::closeStyle(PromoLanding::Hospitality))->toBe('scrim');
});

it('levert industry-foto’s wanneer de bestanden bestaan', function () {
    $visuals = SectorLandingVisuals::for(PromoLanding::Industry);

    expect($visuals)->toHaveKeys(['hero', 'problem', 'steps', 'places', 'roles', 'why', 'close'])
        ->and($visuals['hero'])->toBe('images/landing/industry/image_01.jpg')
        ->and($visuals['close'])->toBe('images/landing/industry/image_06.jpg')
        ->and(SectorLandingVisuals::modifiers(PromoLanding::Industry))
        ->toHaveKey('steps')
        ->and(SectorLandingVisuals::layouts(PromoLanding::Industry))
        ->toHaveKey('places')
        ->and(SectorLandingVisuals::closeStyle(PromoLanding::Industry))->toBe('scrim');
});
