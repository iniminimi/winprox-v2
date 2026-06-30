<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Support\Qr\QrCodePngWriter;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\SimpleType\Jc;
use RuntimeException;
use DOMDocument;
use DOMElement;
use DOMNode;

final class PromoCampaignLetterDocxBuilder
{
    private const BODY_FONT_PT = 11;

    private const FLOW_IMAGE_WIDTH_CM = 9.8;

    private const QR_IMAGE_WIDTH_CM = 3.0;

    private const QR_IMAGE_HEIGHT_CM = 3.0;

    private const CLOSING_TABLE_WIDTH_CM = 16.0;

    private const CLOSING_TABLE_COLUMN_CM = 8.0;

    private const BODY_PARAGRAPH_SPACE_AFTER_TWIP = 240;

    private const LIST_ITEM_SPACE_AFTER_TWIP = 80;

    private const BULLET_LIST_STYLE = 'promoCampaignLetterBullets';

    private const ORDERED_LIST_STYLE = 'promoCampaignLetterOrdered';

    /**
     * @param  array<string, string>  $placeholders
     */
    public function build(
        string $locale,
        array $placeholders,
        string $letterBodyHtml,
        ?string $flowImagePath,
        string $promoUrl,
        string $outputPath,
    ): void {
        if (! QrCodePngWriter::canGenerate()) {
            throw new RuntimeException('QR generation is not available on this system.');
        }

        $letterBodyHtml = PromoCampaignPlaceholderRenderer::render($letterBodyHtml, $placeholders);

        $tempFiles = [];
        $qrPath = tempnam(sys_get_temp_dir(), 'wp-promo-campaign-qr-');
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
        $this->registerListStyles($phpWord);

        $section = $phpWord->addSection([
            'marginTop' => Converter::cmToTwip(2.0),
            'marginBottom' => Converter::cmToTwip(0.8),
            'marginLeft' => Converter::cmToTwip(3.0),
            'marginRight' => Converter::cmToTwip(2.0),
        ]);

        foreach ($this->addressLines($locale, $placeholders) as $line) {
            $this->addParagraph($section, $line, ['spaceAfter' => 0]);
        }

        $this->addBlankLine($section);
        $this->addParagraph($section, $this->greetingLine($locale), ['spaceAfter' => 0]);
        $this->addBlankLine($section);
        $this->addBlankLine($section);

        if ($letterBodyHtml !== '') {
            $this->addLetterBodyHtml($section, $letterBodyHtml, $locale, $flowImagePath);
        } elseif ($flowImagePath !== null && $flowImagePath !== '' && is_file($flowImagePath)) {
            $section->addImage($flowImagePath, [
                'width' => Converter::cmToPixel(self::FLOW_IMAGE_WIDTH_CM),
                'alignment' => Jc::CENTER,
            ]);
        }

        $this->addBlankLine($section);
        $this->addClosingWithQr($section, $qrPngPath, $locale);

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
     * @param  array<string, string>  $placeholders
     * @return list<string>
     */
    private function addressLines(string $locale, array $placeholders): array
    {
        $name = $placeholders['name'] ?? '';
        $attention = match (strtolower(substr($locale, 0, 2))) {
            'fr' => "À l'attention de {$name}",
            default => 'T.a.v. '.$name,
        };

        $postalLine = trim(($placeholders['postal_code'] ?? '').' '.($placeholders['city'] ?? ''));

        return array_values(array_filter([
            $attention,
            $placeholders['street_address'] ?? '',
            $postalLine,
        ], static fn (string $line): bool => trim($line) !== ''));
    }

    private function greetingLine(string $locale): string
    {
        return match (strtolower(substr($locale, 0, 2))) {
            'fr' => 'Madame, Monsieur,',
            default => 'Geachte,',
        };
    }

    private function addClosingWithQr(Section $section, string $qrPngPath, string $locale): void
    {
        $closing = match (strtolower(substr($locale, 0, 2))) {
            'fr' => 'Dans l\'attente de votre retour, je vous prie d\'agréer, Madame, Monsieur, l\'expression de mes salutations distinguées.',
            default => 'Met vriendelijke groet,',
        };

        $this->addParagraph($section, $closing, ['spaceAfter' => 0]);
        $this->addBlankLine($section);
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
        $this->addCellParagraph($textCell, match (strtolower(substr($locale, 0, 2))) {
            'fr' => 'Fondateur / Architecte WinProx',
            default => 'Oprichter / Architect WinProx',
        }, ['spaceAfter' => 0]);
        $this->addCellParagraph($textCell, 'gsm : 0494/840854', ['spaceAfter' => 0]);
        $this->addCellParagraph($textCell, 'info@winprox.app', ['spaceAfter' => 0]);
        $this->addCellParagraph($textCell, 'www.winprox.app', ['spaceAfter' => 0]);

        $qrCell->addImage($qrPngPath, [
            'width' => Converter::cmToPixel(self::QR_IMAGE_WIDTH_CM),
            'height' => Converter::cmToPixel(self::QR_IMAGE_HEIGHT_CM),
            'alignment' => Jc::START,
        ]);
    }

    private function addLetterBodyHtml(Section $section, string $letterBodyHtml, string $locale, ?string $flowImagePath): void
    {
        $placeholder = PromoCampaignQuillHtmlNormalizer::FLOW_IMAGE_PLACEHOLDER;
        $canInsertFlow = $flowImagePath !== null && $flowImagePath !== '' && is_file($flowImagePath);

        if ($canInsertFlow && str_contains($letterBodyHtml, $placeholder)) {
            $letterBodyHtml = preg_replace(
                '/<p[^>]*>\s*'.preg_quote($placeholder, '/').'\s*<\/p>/iu',
                $placeholder,
                $letterBodyHtml,
            ) ?? $letterBodyHtml;

            $parts = explode($placeholder, $letterBodyHtml, 2);
            $before = PromoCampaignQuillHtmlNormalizer::forDocx($parts[0], $locale);
            $after = PromoCampaignQuillHtmlNormalizer::forDocx($parts[1] ?? '', $locale);

            if ($before !== '') {
                $this->renderHtmlBlocks($section, $before);
            }

            $section->addImage($flowImagePath, [
                'width' => Converter::cmToPixel(self::FLOW_IMAGE_WIDTH_CM),
                'alignment' => Jc::CENTER,
            ]);

            if ($after !== '') {
                $this->renderHtmlBlocks($section, $after);
            }

            return;
        }

        $prepared = PromoCampaignQuillHtmlNormalizer::forDocx($letterBodyHtml, $locale);
        if ($prepared === '') {
            return;
        }

        $this->renderHtmlBlocks($section, $prepared);

        if ($canInsertFlow) {
            $section->addImage($flowImagePath, [
                'width' => Converter::cmToPixel(self::FLOW_IMAGE_WIDTH_CM),
                'alignment' => Jc::CENTER,
            ]);
        }
    }

    private function registerListStyles(PhpWord $phpWord): void
    {
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

        $phpWord->addNumberingStyle(self::ORDERED_LIST_STYLE, [
            'type' => 'multilevel',
            'levels' => [
                [
                    'format' => 'decimal',
                    'text' => '%1.',
                    'left' => Converter::cmToTwip(0.5),
                    'hanging' => Converter::cmToTwip(0.35),
                    'tabPos' => Converter::cmToTwip(0.85),
                    'start' => 1,
                    'alignment' => 'left',
                ],
            ],
        ]);
    }

    private function renderHtmlBlocks(Section $section, string $html): void
    {
        if (trim($html) === '') {
            return;
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $container = $document->getElementsByTagName('div')->item(0);
        if ($container === null) {
            Html::addHtml($section, $html, false, false);

            return;
        }

        foreach ($container->childNodes as $node) {
            $this->renderBodyNode($section, $node);
        }
    }

    private function renderBodyNode(Section $section, DOMNode $node): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = trim($node->textContent ?? '');
            if ($text !== '') {
                $this->addParagraph($section, $text, ['spaceAfter' => 120]);
            }

            return;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }

        $tag = strtolower($node->nodeName);
        if ($tag === 'p') {
            $inner = $this->nodeInnerHtml($node);
            if (PromoCampaignHtmlSanitizer::isBlank($inner)) {
                $this->addBlankLine($section);

                return;
            }

            $textRun = $section->addTextRun([
                'spaceAfter' => self::BODY_PARAGRAPH_SPACE_AFTER_TWIP,
            ]);
            Html::addHtml($textRun, $inner, false, false);

            return;
        }

        if ($tag === 'ul' || $tag === 'ol') {
            $listStyle = $tag === 'ol' ? self::ORDERED_LIST_STYLE : self::BULLET_LIST_STYLE;
            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'li') {
                    $this->renderListItem($section, $child, $listStyle);
                }
            }
        }
    }

    private function renderListItem(Section $section, DOMElement $li, string $listStyle): void
    {
        $inner = $this->nodeInnerHtml($li);
        if (PromoCampaignHtmlSanitizer::isBlank($inner)) {
            return;
        }

        $item = $section->addListItemRun(0, $listStyle, [
            'spaceAfter' => self::LIST_ITEM_SPACE_AFTER_TWIP,
            'spaceBefore' => 0,
        ]);
        Html::addHtml($item, $inner, false, false);
    }

    private function nodeInnerHtml(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?? '';
        }

        return trim($html);
    }

    private function addBlankLine(Section $section): void
    {
        $this->addParagraph($section, '', ['spaceAfter' => 0]);
    }

    private function addCellBlankLine(Cell $cell): void
    {
        $this->addCellParagraph($cell, '', ['spaceAfter' => 0]);
    }

    /**
     * @param  array{bold?: bool, spaceAfter?: int, spaceBefore?: int}  $style
     */
    private function addParagraph(Section $section, string $text, array $style = []): void
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
    private function addCellParagraph(Cell $cell, string $text, array $style = []): void
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
