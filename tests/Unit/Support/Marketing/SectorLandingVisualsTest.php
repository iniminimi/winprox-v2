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

it('levert government-foto’s wanneer de bestanden bestaan', function () {
    $visuals = SectorLandingVisuals::for(PromoLanding::Government);

    expect($visuals)->toHaveKeys(['hero', 'problem', 'steps', 'places', 'roles', 'close'])
        ->and($visuals)->not->toHaveKey('why')
        ->and($visuals['hero'])->toBe('images/landing/gouvernment/image_01.jpg')
        ->and($visuals['close'])->toBe('images/landing/gouvernment/image_05.jpg')
        ->and(SectorLandingVisuals::modifiers(PromoLanding::Government))
        ->toHaveKey('steps')
        ->and(SectorLandingVisuals::layouts(PromoLanding::Government))
        ->toHaveKey('places')
        ->and(SectorLandingVisuals::closeStyle(PromoLanding::Government))->toBe('scrim')
        ->and(SectorLandingVisuals::modifiers(PromoLanding::Government)['close'] ?? null)
        ->toBe('wp-landing-close__photo--lower');
});

it('levert healthcare-foto’s wanneer de bestanden bestaan', function () {
    $visuals = SectorLandingVisuals::for(PromoLanding::Healthcare);

    expect($visuals)->toHaveKeys(['hero', 'problem', 'steps', 'places', 'roles', 'why', 'close'])
        ->and($visuals['hero'])->toBe('images/landing/healthcare/05.jpg')
        ->and($visuals['close'])->toBe('images/landing/healthcare/image_03.jpg')
        ->and(SectorLandingVisuals::modifiers(PromoLanding::Healthcare))
        ->toHaveKey('steps')
        ->and(SectorLandingVisuals::layouts(PromoLanding::Healthcare))
        ->toHaveKey('places')
        ->and(SectorLandingVisuals::closeStyle(PromoLanding::Healthcare))->toBe('scrim');
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

it('levert schoonmaak- en bouwfoto’s wanneer de bestanden bestaan', function () {
    $visuals = SectorLandingVisuals::for(PromoLanding::WorkOnLocation);

    expect($visuals)->toHaveKeys(['hero', 'problem', 'steps', 'places', 'roles', 'why', 'close'])
        ->and($visuals['hero'])->toBe('images/landing/work_on_location/image01.jpg')
        ->and($visuals['problem'])->toBe('images/landing/work_on_location/image02.jpg')
        ->and($visuals['places'])->toBe('images/landing/work_on_location/image04.jpg')
        ->and($visuals['roles'])->toBe('images/landing/work_on_location/image03.jpg')
        ->and($visuals['close'])->toBe('images/landing/general/welcome_07.jpg')
        ->and(is_file(public_path($visuals['hero'])))->toBeTrue()
        ->and(SectorLandingVisuals::modifiers(PromoLanding::WorkOnLocation))
        ->toHaveKey('steps')
        ->and(SectorLandingVisuals::layouts(PromoLanding::WorkOnLocation))
        ->toHaveKey('places')
        ->and(SectorLandingVisuals::closeStyle(PromoLanding::WorkOnLocation))->toBe('scrim');
});
