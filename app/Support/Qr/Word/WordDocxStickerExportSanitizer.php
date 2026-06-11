<?php

declare(strict_types=1);

namespace App\Support\Qr\Word;

use App\Support\Qr\QrStickerSheetTemplate;
use RuntimeException;
use ZipArchive;

/**
 * Sticker Word exports: plain white cells (QR + text only).
 * PhpWord may emit black table borders and gray cell shading — strip those here.
 */
final class WordDocxStickerExportSanitizer
{
    public static function apply(string $absoluteDocxPath, QrStickerSheetTemplate $template): void
    {
        $zip = new ZipArchive;
        if ($zip->open($absoluteDocxPath) !== true) {
            throw new RuntimeException('Unable to open generated DOCX for sticker export sanitizing.');
        }

        try {
            self::patchSettings($zip);
            self::patchDocument($zip, $template);
        } finally {
            $zip->close();
        }
    }

    private static function patchSettings(ZipArchive $zip): void
    {
        $settingsXml = $zip->getFromName('word/settings.xml');
        if ($settingsXml === false) {
            throw new RuntimeException('Generated DOCX is missing word/settings.xml.');
        }

        if (! str_contains($settingsXml, 'w:printBackground')) {
            $settingsXml = str_replace(
                '</w:settings>',
                '<w:printBackground w:val="0"/><w:displayBackgroundShape w:val="0"/></w:settings>',
                $settingsXml,
            );
        }

        if ($zip->addFromString('word/settings.xml', $settingsXml) === false) {
            throw new RuntimeException('Unable to patch word/settings.xml in generated DOCX.');
        }
    }

    private static function patchDocument(ZipArchive $zip, QrStickerSheetTemplate $template): void
    {
        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml === false) {
            throw new RuntimeException('Generated DOCX is missing word/document.xml.');
        }

        $documentXml = preg_replace('/<w:shd\b[^>]*\/>/', '', $documentXml) ?? $documentXml;
        $documentXml = preg_replace('/<w:shd\b[^>]*>.*?<\/w:shd>/s', '', $documentXml) ?? $documentXml;
        $documentXml = preg_replace('/<w:tblBorders>.*?<\/w:tblBorders>/s', '', $documentXml) ?? $documentXml;
        $documentXml = preg_replace('/<w:tcBorders>.*?<\/w:tcBorders>/s', '', $documentXml) ?? $documentXml;
        $documentXml = match ($template) {
            QrStickerSheetTemplate::Avery55x55S => Avery55x55StickerTableLayout::patchDocument($documentXml),
            QrStickerSheetTemplate::Herma7050 => Herma7050StickerTableLayout::patchDocument($documentXml),
            QrStickerSheetTemplate::Avery62x89R => Avery62x89StickerTableLayout::patchDocument($documentXml),
        };

        if ($zip->addFromString('word/document.xml', $documentXml) === false) {
            throw new RuntimeException('Unable to patch word/document.xml in generated DOCX.');
        }
    }
}
