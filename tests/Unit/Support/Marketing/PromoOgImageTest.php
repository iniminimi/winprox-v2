<?php

declare(strict_types=1);

use App\Support\Marketing\PromoOgImage;

it('kiest een og jpeg uit de promo map', function (): void {
    $og = PromoOgImage::random();

    expect($og)->toHaveKeys(['url', 'width', 'height', 'type'])
        ->and($og['url'])->toContain('/images/promo/og_')
        ->and($og['width'])->toBeGreaterThan(0)
        ->and($og['height'])->toBeGreaterThan(0)
        ->and($og['type'])->toBe('image/jpeg');
});
