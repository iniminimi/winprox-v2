<?php

use App\Actions\Marketing\GenerateMunicipalPromoLettersAction;
use App\Models\PromoRecipient;
use App\Models\User;
use App\Support\Marketing\FlemishMunicipalitiesSpreadsheetReader;
use App\Support\Qr\QrCodePngWriter;

function municipalSpreadsheetFixturePath(): string
{
    return base_path('tests/Vlaanderen_lokale_besturen.xlsx');
}

it('leest Vlaamse gemeenten uit de spreadsheet', function () {
    $path = municipalSpreadsheetFixturePath();
    if (! is_file($path)) {
        $this->markTestSkipped('Spreadsheet fixture ontbreekt lokaal.');
    }

    $rows = app(FlemishMunicipalitiesSpreadsheetReader::class)->read($path);

    expect($rows)->not->toBeEmpty()
        ->and($rows[0]->name)->not->toBe('')
        ->and($rows[0]->streetAddress)->not->toBe('')
        ->and($rows[0]->postalCode)->not->toBe('');
});

it('genereert een lokale gemeentebrief met unieke promo-bestemmeling', function () {
    if (! QrCodePngWriter::canGenerate()) {
        $this->markTestSkipped('QR generation unavailable.');
    }

    $path = municipalSpreadsheetFixturePath();
    if (! is_file($path)) {
        $this->markTestSkipped('Spreadsheet fixture ontbreekt lokaal.');
    }

    $superuser = User::factory()->superuser()->create();
    $outputDirectory = storage_path('framework/testing/municipal-promo-letters-'.uniqid('', true));

    $result = app(GenerateMunicipalPromoLettersAction::class)->handle(
        spreadsheetPath: $path,
        outputDirectory: $outputDirectory,
        flowImagePath: base_path('public/images/promo/flow.jpg'),
        actorUserId: (int) $superuser->id,
        limit: 1,
        overwriteExisting: true,
    );

    expect($result['generated'])->toBe(1)
        ->and($result['recipients_created'])->toBe(1)
        ->and(PromoRecipient::query()->count())->toBe(1);

    $files = glob($outputDirectory.'/*.docx') ?: [];
    expect($files)->toHaveCount(1)
        ->and(filesize($files[0]))->toBeGreaterThan(10_000);

    array_map(static fn (string $file): bool => unlink($file), $files);
    @rmdir($outputDirectory);
});
