<?php

declare(strict_types=1);

namespace App\Support\Marketing;

final class PromoCampaignQuillHtmlNormalizer
{
    public const FLOW_IMAGE_PLACEHOLDER = '{{flow_image}}';

    public static function normalize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        if (str_contains($html, '&lt;') || str_contains($html, '&gt;') || str_contains($html, '&amp;')) {
            $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (str_contains($html, 'data-list="bullet"')) {
            $html = preg_replace('/<ol(\s[^>]*)?>/', '<ul>', $html) ?? $html;
            $html = preg_replace('/<\/ol>/', '</ul>', $html) ?? $html;
        }

        $html = preg_replace('/\s+data-list="[^"]*"/', '', $html) ?? $html;
        $html = preg_replace('/<br\s*>/', '<br/>', $html) ?? $html;

        return (string) (PromoCampaignHtmlSanitizer::clean($html) ?? '');
    }

    /**
     * E-mail body: lege Quill-paragrafen weg (voorkomt dubbele witruimte in mail-template).
     */
    public static function forMail(?string $html): string
    {
        $html = self::normalize($html);
        if ($html === '') {
            return '';
        }

        return self::collapseEmptyParagraphs($html);
    }

    /**
     * Brief-middenstuk voor DOCX: lege Quill-paragrafen weg, lijsten naar bullets, compacte spacing.
     */
    public static function forDocx(?string $html, string $locale): string
    {
        $html = self::normalize($html);
        if ($html === '') {
            return '';
        }

        $html = self::collapseEmptyParagraphs($html);
        $html = self::stripDuplicateEnvelope($html, $locale);
        $html = self::stripDuplicateClosing($html, $locale);
        $html = self::applyDocxBodyParagraphSpacing($html);

        return self::collapseEmptyParagraphs($html);
    }

    private static function collapseEmptyParagraphs(string $html): string
    {
        $previous = null;
        while ($previous !== $html) {
            $previous = $html;
            $html = preg_replace(
                '/<p[^>]*>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>/iu',
                '',
                $html,
            ) ?? $html;
        }

        return trim($html);
    }

    private static function stripDuplicateEnvelope(string $html, string $locale): string
    {
        $greeting = self::greetingForLocale($locale);
        $head = substr($html, 0, 1200);
        $pos = stripos($head, $greeting);
        if ($pos === false) {
            return $html;
        }

        $paragraphStart = strrpos(substr($html, 0, $pos), '<p');
        if ($paragraphStart === false) {
            return $html;
        }

        $closeParagraph = stripos($html, '</p>', $pos + strlen($greeting));
        if ($closeParagraph === false) {
            return $html;
        }

        $paragraphHtml = substr($html, $paragraphStart, $closeParagraph - $paragraphStart + 4);
        $paragraphText = trim(preg_replace('/\s+/u', ' ', strip_tags($paragraphHtml)) ?? '');
        if ($paragraphText === '' || mb_strlen($paragraphText) > 80) {
            return $html;
        }

        if (! str_starts_with(mb_strtolower($paragraphText), mb_strtolower(substr($greeting, 0, 12)))) {
            return $html;
        }

        return trim(substr($html, $closeParagraph + 4));
    }

    private static function stripDuplicateClosing(string $html, string $locale): string
    {
        $cutAt = null;

        foreach (self::closingMarkersForLocale($locale) as $marker) {
            $pos = stripos($html, $marker);
            if ($pos !== false && ($cutAt === null || $pos < $cutAt)) {
                $cutAt = $pos;
            }
        }

        $signaturePos = stripos($html, 'Dominique Schaepdrijver');
        if ($signaturePos !== false && ($cutAt === null || $signaturePos < $cutAt)) {
            $cutAt = $signaturePos;
        }

        if ($cutAt === null) {
            return $html;
        }

        $paragraphStart = strrpos(substr($html, 0, $cutAt), '<p');
        if ($paragraphStart !== false) {
            return trim(substr($html, 0, $paragraphStart));
        }

        return trim(substr($html, 0, $cutAt));
    }

    private static function applyDocxBodyParagraphSpacing(string $html): string
    {
        return preg_replace_callback(
            '/<p(\s[^>]*)?>/i',
            static function (array $matches): string {
                $attrs = $matches[1] ?? '';
                if (str_contains($attrs, 'margin-bottom')) {
                    return $matches[0];
                }

                return '<p style="margin-bottom:6pt"'.$attrs.'>';
            },
            $html,
        ) ?? $html;
    }

    private static function greetingForLocale(string $locale): string
    {
        return match (strtolower(substr($locale, 0, 2))) {
            'fr' => 'Madame, Monsieur,',
            default => 'Geachte,',
        };
    }

    /**
     * @return list<string>
     */
    private static function closingMarkersForLocale(string $locale): array
    {
        return match (strtolower(substr($locale, 0, 2))) {
            'fr' => [
                "Dans l'attente de votre retour",
                'Met vriendelijke groet',
            ],
            default => [
                'Met vriendelijke groet',
                "Dans l'attente de votre retour",
            ],
        };
    }
}
