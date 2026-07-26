<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

it('translation:import is a no-op when no file is present', function () {
    $path = storage_path('app/imports/translated.json');
    File::ensureDirectoryExists(dirname($path));
    if (File::exists($path)) {
        File::delete($path);
    }

    Artisan::call('translation:import');

    expect(Artisan::output())->toContain('No import file present')
        ->and(Artisan::call('translation:import'))->toBe(0);
});

it('translation:import deletes the file after a successful run', function () {
    $path = storage_path('app/imports/translated.json');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, json_encode(['items' => []], JSON_THROW_ON_ERROR));

    expect(Artisan::call('translation:import'))->toBe(0)
        ->and(File::exists($path))->toBeFalse()
        ->and(Artisan::output())->toContain('Imported 0 translation(s).');
});
