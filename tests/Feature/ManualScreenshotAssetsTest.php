<?php

use App\Support\Manual\ManualScreenshotAssets;

it('mapt een hoofdstuksleutel naar een bestandsnaam', function () {
    expect(ManualScreenshotAssets::filenameForChapter('issues.list'))
        ->toBe('issues-list.png');
});

it('geeft een public url wanneer het screenshot-bestand bestaat', function () {
    $dir = public_path('images/manual/nl');
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $file = $dir.'/dashboard.png';
    file_put_contents($file, 'png');

    $url = ManualScreenshotAssets::publicUrl('dashboard', 'nl');

    expect($url)->not->toBeNull()
        ->and($url)->toContain('images/manual/nl/dashboard.png');

    unlink($file);
});

it('geeft null wanneer het screenshot-bestand ontbreekt', function () {
    expect(ManualScreenshotAssets::publicUrl('missing.screenshot.test', 'nl'))->toBeNull()
        ->and(ManualScreenshotAssets::publicUrlForCaptureId('missing-capture-id', 'nl'))->toBeNull();
});

it('geeft een public url voor een capture-id wanneer het bestand bestaat', function () {
    $dir = public_path('images/manual/nl');
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $file = $dir.'/test-capture-id.png';
    file_put_contents($file, 'png');

    $url = ManualScreenshotAssets::publicUrlForCaptureId('test-capture-id', 'nl');

    expect($url)->not->toBeNull()
        ->and($url)->toContain('images/manual/nl/test-capture-id.png');

    unlink($file);
});

it('herkent internetportaal-hoofdstukken', function () {
    expect(ManualScreenshotAssets::isPortalChapter('portal.unit'))->toBeTrue()
        ->and(ManualScreenshotAssets::isPortalChapter('dashboard'))->toBeFalse();
});
