<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Data\Marketing\MunicipalPromoLetterData;
use App\Support\Qr\QrCodePngWriter;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use RuntimeException;

final class MunicipalPromoLetterDocxBuilder
{
    private const BODY_FONT_PT = 11;

    private const FLOW_IMAGE_WIDTH_CM = 9.8;

    private const QR_IMAGE_WIDTH_CM = 3.0;

    private const QR_IMAGE_HEIGHT_CM = 3.0;

    private const CLOSING_TABLE_WIDTH_CM = 16.0;

    private const CLOSING_TABLE_COLUMN_CM = 8.0;

    private const BULLET_LIST_STYLE = 'municipalPromoLetterBullets';

    public function build(
        MunicipalPromoLetterData $municipality,
        string $promoUrl,
        string $flowImagePath,
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
        $phpWord->addNumberingStyle(self::BULLET_LIST_STYLE, [
            'type' => 'hybridMultilevel',
            'levels' => [
                [
                    'format' => 'bullet',
                    'text' => '•',
                    'left' => Converter::cmToTwip(0.5),
                    'hanging' => Converter::cmToTwip(0.35),
                    'tabPos' => Converter::cmToTwip(0.85),
                    'start' => 1,
                    'alignment' => 'left',
                ],
            ],
        ]);

        $section = $phpWord->addSection([
            'marginTop' => Converter::cmToTwip(2.0),
            'marginBottom' => Converter::cmToTwip(0.8),
            'marginLeft' => Converter::cmToTwip(3.0),
            'marginRight' => Converter::cmToTwip(2.0),
        ]);

        foreach ($municipality->addressLines() as $line) {
            $this->addParagraph($section, $line, ['spaceAfter' => 0]);
        }

        $this->addBlankLine($section);

        $this->addParagraph($section, 'Betreft: Efficiënter beheer van de publieke ruimte.', [
            'spaceAfter' => 0,
        ]);

        $this->addBlankLine($section);
        $this->addBlankLine($section);

        $this->addParagraph($section, 'Geachte,', [
            'spaceAfter' => 0,
        ]);

        $this->addBlankLine($section);
        $this->addBlankLine($section);

        $this->addParagraph(
            $section,
            'Het beheer van publieke infrastructuur kan uitdagend zijn, maar het kan ook eenvoudig en efficiënt.   Graag stel ik u WinProx voor, een platform dat beheer digitaliseert via slimme QR-technologie.',
            ['spaceAfter' => 120],
        );
        $this->addParagraph(
            $section,
            'De essentie is heel simpel :',
            ['spaceAfter' => 0],
        );

        $section->addImage($flowImagePath, [
            'width' => Converter::cmToPixel(self::FLOW_IMAGE_WIDTH_CM),
            'alignment' => Jc::CENTER,
        ]);

        $this->addParagraph(
            $section,
            'Zonder app-installatie kunnen burgers of medewerkers een object of locatie scannen en direct een melding maken met foto’s.   Op het geopende portaal kunnen documenten en mededelingen geraadpleegd worden.',
            ['spaceAfter' => 120, 'spaceBefore' => 80],
        );

        $this->addParagraph($section, 'De voordelen voor de gemeente:', [
            'bold' => true,
            'spaceAfter' => 40,
        ]);

        foreach ($this->advantageLines() as [$label, $body]) {
            $this->addBulletItem($section, $label, $body);
        }

        $this->addBlankLine($section);

        $this->addParagraph(
            $section,
            'Ik kom dit systeem graag in 15 minuten aan u demonstreren. Via de onderstaande QR-code kunt u alvast korte demonstratievideo’s bekijken die de werking in de praktijk tonen.',
            ['spaceAfter' => 0],
        );

        $this->addBlankLine($section);
        $this->addBlankLine($section);
        $this->addBlankLine($section);

        $this->addClosingWithQr($section, $qrPngPath);

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

    private function addClosingWithQr(\PhpOffice\PhpWord\Element\Section $section, string $qrPngPath): void
    {
        $this->addParagraph($section, 'Met vriendelijke groet,', ['spaceAfter' => 0]);
        $this->addBlankLine($section);

        $table = $section->addTable([
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
            'cellMargin' => 0,
            'width' => Converter::cmToTwip(self::CLOSING_TABLE_WIDTH_CM),
            'unit' => 'dxa',
        ]);

        $table->addRow();
        $textCell = $table->addCell(Converter::cmToTwip(self::CLOSING_TABLE_COLUMN_CM), [
            'valign' => 'top',
            'borderSize' => 0,
        ]);
        $qrCell = $table->addCell(Converter::cmToTwip(self::CLOSING_TABLE_COLUMN_CM), [
            'valign' => 'center',
            'borderSize' => 0,
        ]);

        $this->addCellParagraph($textCell, 'Dominique Schaepdrijver', ['spaceAfter' => 0]);
        $this->addCellParagraph($textCell, 'Oprichter / Architect WinProx', ['spaceAfter' => 0]);
        $this->addCellParagraph($textCell, 'info@winprox.app', ['spaceAfter' => 0]);
        $this->addCellParagraph($textCell, 'www.winprox.app', ['spaceAfter' => 0]);

        $qrCell->addImage($qrPngPath, [
            'width' => Converter::cmToPixel(self::QR_IMAGE_WIDTH_CM),
            'height' => Converter::cmToPixel(self::QR_IMAGE_HEIGHT_CM),
            'alignment' => Jc::START,
        ]);
    }

    private function addBulletItem(\PhpOffice\PhpWord\Element\Section $section, string $label, string $body): void
    {
        $item = $section->addListItemRun(0, self::BULLET_LIST_STYLE, [
            'spaceAfter' => 24,
            'spaceBefore' => 0,
        ]);
        $item->addText($label.':', [
            'name' => 'Arial',
            'size' => self::BODY_FONT_PT,
            'bold' => true,
        ]);
        $item->addTextBreak();
        $item->addText($body, [
            'name' => 'Arial',
            'size' => self::BODY_FONT_PT,
        ]);
    }

    private function addBlankLine(\PhpOffice\PhpWord\Element\Section $section): void
    {
        $this->addParagraph($section, '', ['spaceAfter' => 0]);
    }

    private function addCellBlankLine(\PhpOffice\PhpWord\Element\Cell $cell): void
    {
        $this->addCellParagraph($cell, '', ['spaceAfter' => 0]);
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
     * @param  array{bold?: bool, spaceAfter?: int, spaceBefore?: int}  $style
     */
    private function addCellParagraph(\PhpOffice\PhpWord\Element\Cell $cell, string $text, array $style = []): void
    {
        $cell->addText($text, [
            'name' => 'Arial',
            'size' => self::BODY_FONT_PT,
            'bold' => (bool) ($style['bold'] ?? false),
        ], [
            'spaceAfter' => $style['spaceAfter'] ?? 40,
            'spaceBefore' => $style['spaceBefore'] ?? 0,
        ]);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function advantageLines(): array
    {
        return [
            ['Centraal beheer', 'Alle meldingen en uitgevoerde werken komen samen in één overzichtelijk systeem.'],
            ['Efficiënter onderhoud', 'Onderhoudsploegen scannen dezelfde QR-codes om taken af te handelen. Foto’s en GPS-locaties worden automatisch geregistreerd. Navigatie naar de exacte locatie via Google Maps is geïntegreerd, ook in bos- en natuurgebieden.'],
            ['Inclusieve communicatie', 'Het platform ondersteunt automatische AI-vertaling van meldingen, taken en mededelingen naar zes talen, zodat informatie toegankelijk blijft voor zowel bezoekers als anderstalige medewerkers en uitvoerders.'],
            ['Naadloze integratie', 'Bestaande gegevens kunnen eenvoudig worden geïmporteerd via CSV of gekoppeld via API’s en webhooks. Dynamische QR-codes kunnen worden afgedrukt in de huisstijl van de gemeente.'],
        ];
    }

    private function saveToPath(PhpWord $phpWord, string $outputPath): void
    {
        $directory = dirname($outputPath);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create output directory: {$directory}");
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($outputPath);
        $this->stripTableBorders($outputPath);
    }

    private function stripTableBorders(string $outputPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($outputPath) !== true) {
            throw new RuntimeException('Unable to open generated DOCX for border sanitizing.');
        }

        try {
            $documentXml = $zip->getFromName('word/document.xml');
            if ($documentXml === false) {
                throw new RuntimeException('Generated DOCX is missing word/document.xml.');
            }

            $documentXml = preg_replace('/<w:tblBorders>.*?<\/w:tblBorders>/s', '', $documentXml) ?? $documentXml;
            $documentXml = preg_replace('/<w:tcBorders>.*?<\/w:tcBorders>/s', '', $documentXml) ?? $documentXml;

            if ($zip->addFromString('word/document.xml', $documentXml) === false) {
                throw new RuntimeException('Unable to patch word/document.xml in generated DOCX.');
            }
        } finally {
            $zip->close();
        }
    }
}
