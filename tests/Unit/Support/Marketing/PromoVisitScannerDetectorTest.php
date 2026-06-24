<?php

use App\Support\Marketing\PromoVisitScannerDetector;

it('herkent bekende mailscanner user-agents', function (string $userAgent) {
    expect(PromoVisitScannerDetector::isAutomatedFetch($userAgent))->toBeTrue();
})->with([
    'Microsoft Safe Links' => ['Mozilla/5.0 (compatible; SafeLinks)'],
    'Proofpoint' => ['Proofpoint URL Defense'],
    'Mimecast' => ['Mimecast Service'],
    'curl' => ['curl/7.88.1'],
    'python-requests' => ['python-requests/2.31.0'],
]);

it('herkent normale browser user-agents niet als scanner', function (string $userAgent) {
    expect(PromoVisitScannerDetector::isAutomatedFetch($userAgent))->toBeFalse();
})->with([
    'Chrome Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'],
    'Safari iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'],
    'Firefox' => ['Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0'],
]);

it('behandelt lege user-agent niet als scanner', function () {
    expect(PromoVisitScannerDetector::isAutomatedFetch(null))->toBeFalse()
        ->and(PromoVisitScannerDetector::isAutomatedFetch(''))->toBeFalse()
        ->and(PromoVisitScannerDetector::isAutomatedFetch('   '))->toBeFalse();
});
