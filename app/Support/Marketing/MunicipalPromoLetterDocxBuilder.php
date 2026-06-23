<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Data\Marketing\MunicipalPromoLetterData;
use App\Support\Qr\QrCodePngWriter;
use Carbon\CarbonInterface;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use RuntimeException;

final class MunicipalPromoLetterDocxBuilder
{
    private const BODY_FONT_PT = 8.5;

    private const FLOW_IMAGE_WIDTH_CM = 15.5;

    private const QR_IMAGE_WIDTH_CM = 3.2;

    public function build(
        MunicipalPromoLetterData $municipality,
        string $promoUrl,
        string $flowImagePath,
        CarbonInterface $letterDate,
        string $outputPath,
    ): void {
        if (! is_file($flowImagePath)) {
            throw new RuntimeException("Flow image not found: {$flowImagePath}");
        }

        if (! QrCodePngWriter::canGenerate()) {
            throw new RuntimeException('QR generation is not available on this system.');
        }

        $tempFiles = [];
        $qrPath = tempnam(sys_get_temp_dir(), 'wp-promo-letter-qr-');
        if ($qrPath === false) {
            throw new RuntimeException('Unable to allocate temporary QR path.');
        }

        $qrPngPath = $qrPath.'.png';
        @unlink($qrPath);
        $tempFiles[] = $qrPngPath;

        QrCodePngWriter::writeFileWithWinproxLogo($promoUrl, $qrPngPath, 900);

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(self::BODY_FONT_PT);

        $section = $phpWord->addSection([
            'marginTop' => Converter::cmToTwip(1.0),
            'marginBottom' => Converter::cmToTwip(0.8),
            'marginLeft' => Converter::cmToTwip(2.0),
            'marginRight' => Converter::cmToTwip(2.0),
        ]);

        $this->addParagraph($section, 'Datum: '.$this->formatDutchDate($letterDate), [
            'spaceAfter' => 80,
        ]);

        foreach ($municipality->addressLines() as $line) {
            $this->addParagraph($section, $line, [
                'spaceAfter' => 0,
            ]);
        }

        $this->addParagraph($section, '', ['spaceAfter' => 120]);
        $this->addParagraph($section, 'Betreft: Efficiënter beheer van de publieke ruimte.', [
            'bold' => true,
            'spaceAfter' => 120,
        ]);
        $this->addParagraph($section, 'Geacht college van '.$municipality->name.',', [
            'spaceAfter' => 80,
        ]);

        $this->addParagraph(
            $section,
            'Het beheer van publieke infrastructuur kan uitdagend zijn, maar het kan ook eenvoudig en efficiënt. Graag stel ik u WinProx voor, een platform dat beheer digitaliseert via slimme QR-technologie.',
            ['spaceAfter' => 60],
        );
        $this->addParagraph(
            $section,
            'De essentie is heel simpel: zonder app-installatie kunnen burgers of medewerkers een object of locatie scannen en direct een melding maken met foto\'s. Op het geopende portaal kunnen documenten en mededelingen geraadpleegd worden.',
            ['spaceAfter' => 60],
        );

        $section->addImage($flowImagePath, [
            'width' => Converter::cmToPixel(self::FLOW_IMAGE_WIDTH_CM),
            'alignment' => Jc::CENTER,
        ]);

        $this->addParagraph($section, 'De voordelen voor de gemeente:', [
            'bold' => true,
            'spaceAfter' => 40,
            'spaceBefore' => 40,
        ]);

        foreach ($this->advantageLines() as $line) {
            $this->addParagraph($section, $line, [
                'spaceAfter' => 20,
            ]);
        }

        $this->addParagraph(
            $section,
            'Ik kom dit systeem graag in 15 minuten aan u demonstreren. Via de onderstaande QR-code kunt u alvast korte demonstratievideo\'s bekijken die de werking in de praktijk tonen.',
            ['spaceAfter' => 60],
        );

        $section->addImage($qrPngPath, [
            'width' => Converter::cmToPixel(self::QR_IMAGE_WIDTH_CM),
            'alignment' => Jc::CENTER,
        ]);

        $this->addParagraph($section, 'Met vriendelijke groet,', [
            'spaceBefore' => 80,
            'spaceAfter' => 40,
        ]);
        $this->addParagraph($section, 'Dominique Schaepdrijver', ['spaceAfter' => 0]);
        $this->addParagraph($section, 'Oprichter / Architect WinProx', ['spaceAfter' => 0]);
        $this->addParagraph($section, 'gsm: 0494/840854', ['spaceAfter' => 0]);
        $this->addParagraph($section, 'info@winprox.app', ['spaceAfter' => 0]);
        $this->addParagraph($section, 'www.winprox.app', ['spaceAfter' => 0]);

        try {
            $this->saveToPath($phpWord, $outputPath);
        } finally {
            foreach ($tempFiles as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    /**
     * @param  array{bold?: bool, spaceAfter?: int, spaceBefore?: int}  $style
     */
    private function addParagraph(\PhpOffice\PhpWord\Element\Section $section, string $text, array $style = []): void
    {
        $section->addText($text, [
            'name' => 'Arial',
            'size' => self::BODY_FONT_PT,
            'bold' => (bool) ($style['bold'] ?? false),
        ], [
            'spaceAfter' => $style['spaceAfter'] ?? 40,
            'spaceBefore' => $style['spaceBefore'] ?? 0,
        ]);
    }

    /**
     * @return list<string>
     */
    private function advantageLines(): array
    {
        return [
            'Centraal beheer: alle meldingen en uitgevoerde werken komen samen in één overzichtelijk systeem.',
            'Efficiënter onderhoud: onderhoudsploegen scannen dezelfde QR-codes om taken af te handelen. Foto\'s en GPS-locaties worden automatisch geregistreerd. Navigatie naar de exacte locatie via Google Maps is geïntegreerd, ook in bos- en natuurgebieden.',
            'Inclusieve communicatie: het platform ondersteunt automatische AI-vertaling van meldingen, taken en mededelingen naar zes talen.',
            'Naadloze integratie: bestaande gegevens kunnen eenvoudig worden geïmporteerd via CSV of gekoppeld via API\'s en webhooks. Dynamische QR-codes kunnen worden afgedrukt in de huisstijl van de gemeente.',
        ];
    }

    private function formatDutchDate(CarbonInterface $date): string
    {
        $months = [
            1 => 'januari', 2 => 'februari', 3 => 'maart', 4 => 'april',
            5 => 'mei', 6 => 'juni', 7 => 'juli', 8 => 'augustus',
            9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december',
        ];

        $month = $months[(int) $date->format('n')] ?? $date->format('F');

        return sprintf('%s %s %s', $date->format('j'), $month, $date->format('Y'));
    }

    private function saveToPath(PhpWord $phpWord, string $outputPath): void
    {
        $directory = dirname($outputPath);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create output directory: {$directory}");
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($outputPath);
    }
}
