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

        if (str_contains($html, 'data-list="bullet"') || str_contains($html, 'data-list=\'bullet\'')) {
            $html = preg_replace('/<ol(\s[^>]*)?>/i', '<ul>', $html) ?? $html;
            $html = preg_replace('/<\/ol>/i', '</ul>', $html) ?? $html;
        }

        $html = preg_replace('/\s+data-list="[^"]*"/', '', $html) ?? $html;
        $html = preg_replace('/<span[^>]*\bql-ui\b[^>]*>.*?<\/span>/is', '', $html) ?? $html;
        $html = preg_replace('/<br\s*>/', '<br/>', $html) ?? $html;

        return (string) (PromoCampaignHtmlSanitizer::clean($html) ?? '');
    }

    /**
     * E-mail body: lege Quill-paragrafen weg, blokken met inline marges (e-mailclients negeren vaak <style>).
     */
    public static function forMail(?string $html): string
    {
        $html = self::normalize($html);
        if ($html === '') {
            return '';
        }

        $html = self::collapseEmptyParagraphs($html);

        if (self::lacksBlockStructure($html)) {
            $html = self::plainTextToParagraphHtml($html);
        }

        $html = self::splitSingleParagraphOnBreaks($html);
        $html = self::promoteUniformFontSizeToParagraphs($html);
        $html = self::applyMailBodyParagraphSpacing($html);
        $html = self::compactMailSignatureSpacing($html);

        return self::applyMailLinkStyles($html);
    }

    /**
     * Brief voor DOCX: lijsten naar bullets; handtekeningblok onderaan blijft vast.
     */
    public static function forDocx(?string $html, string $locale): string
    {
        $html = self::normalize($html);
        if ($html === '') {
            return '';
        }

        $html = self::stripDuplicateClosing($html, $locale);
        $html = self::promoteOrderedListsToBulletLists($html);
        $html = self::unwrapListItemParagraphs($html);

        return trim($html);
    }

    private static function unwrapListItemParagraphs(string $html): string
    {
        $previous = null;
        while ($previous !== $html) {
            $previous = $html;
            $html = preg_replace(
                '/<li([^>]*)>\s*<p[^>]*>(.*?)<\/p>\s*<\/li>/is',
                '<li$1>$2</li>',
                $html,
            ) ?? $html;
        }

        return $html;
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

    /**
     * Quill 2 bewaart bullets als <ol>; na opschonen geen data-list meer — voor DOCX altijd <ul>.
     */
    private static function promoteOrderedListsToBulletLists(string $html): string
    {
        if (! str_contains($html, '<ol')) {
            return $html;
        }

        $html = preg_replace('/<ol(\s[^>]*)?>/i', '<ul>', $html) ?? $html;

        return preg_replace('/<\/ol>/i', '</ul>', $html) ?? $html;
    }

    private static function lacksBlockStructure(string $html): bool
    {
        return ! preg_match('/<(p|ul|ol|li|h[1-6]|div|table|blockquote)\b/i', $html);
    }

    private static function plainTextToParagraphHtml(string $text): string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '') {
            return '';
        }

        $blocks = preg_split('/\n\s*\n/', $text) ?: [];
        if (count($blocks) === 1) {
            $lines = array_values(array_filter(
                array_map('trim', explode("\n", $text)),
                static fn (string $line): bool => $line !== '',
            ));

            if ($lines === []) {
                return '';
            }

            return implode('', array_map(
                static fn (string $line): string => '<p>'.htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>',
                $lines,
            ));
        }

        return implode('', array_map(
            static function (string $block): string {
                $block = trim($block);
                if ($block === '') {
                    return '';
                }

                return '<p>'.nl2br(htmlspecialchars($block, ENT_QUOTES | ENT_HTML5, 'UTF-8'), false).'</p>';
            },
            $blocks,
        ));
    }

    private static function splitSingleParagraphOnBreaks(string $html): string
    {
        $trimmed = trim($html);
        if (! preg_match('/^<p[^>]*>(.*)<\/p>$/is', $trimmed, $matches)) {
            return $html;
        }

        $inner = $matches[1];
        if (! preg_match('/<br\s*\/?>/i', $inner)) {
            return $html;
        }

        $parts = preg_split('/<br\s*\/?>/i', $inner) ?: [];
        $paragraphs = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || PromoCampaignHtmlSanitizer::isBlank($part)) {
                continue;
            }

            $paragraphs[] = '<p>'.$part.'</p>';
        }

        return $paragraphs === [] ? $html : implode('', $paragraphs);
    }

    /**
     * Zet een uniforme lettergrootte ook op <p> (Outlook negeert font-size op span/strong).
     */
    private static function promoteUniformFontSizeToParagraphs(string $html): string
    {
        return preg_replace_callback(
            '/<p(\s[^>]*)?>(.*?)<\/p>/is',
            static function (array $matches): string {
                $attrs = $matches[1] ?? '';
                $inner = $matches[2];
                if (PromoCampaignHtmlSanitizer::fontSizeFromAttributes($attrs) !== null) {
                    return $matches[0];
                }

                $size = self::uniformInnerFontSize($inner);
                if ($size === null) {
                    return $matches[0];
                }

                return '<p style="font-size: '.$size.'"'.$attrs.'>'.$inner.'</p>';
            },
            $html,
        ) ?? $html;
    }

    private static function uniformInnerFontSize(string $inner): ?string
    {
        if (preg_match_all('/font-size:\s*(\d{1,2})px/i', $inner, $matches) < 1) {
            return null;
        }

        $unique = array_values(array_unique($matches[1]));
        if (count($unique) !== 1) {
            return null;
        }

        $withoutSized = preg_replace(
            '/<(span|strong|b|em|i|a)([^>]*font-size:[^>]*)>.*?<\/\1>/is',
            '',
            $inner,
        ) ?? $inner;

        if (trim(strip_tags($withoutSized)) !== '') {
            return null;
        }

        $px = (int) $unique[0];
        if ($px < 10 || $px > 36) {
            return null;
        }

        return $px.'px';
    }

    /**
     * E-mailclients negeren vaak <style> op <a>; zonder inline underline lijkt de link platte tekst.
     */
    private static function applyMailLinkStyles(string $html): string
    {
        return preg_replace_callback(
            '/<a(\s[^>]*)?>/i',
            static function (array $matches): string {
                $attrs = $matches[1] ?? '';
                if (preg_match('/\bhref=/i', $attrs) !== 1) {
                    return $matches[0];
                }

                $fontSize = PromoCampaignHtmlSanitizer::fontSizeFromAttributes($attrs);
                $attrs = preg_replace('/\s+style="[^"]*"/', '', $attrs) ?? $attrs;
                $style = 'color:#059669;font-weight:600;text-decoration:underline';
                if ($fontSize !== null) {
                    $style .= ';font-size: '.$fontSize;
                }

                return '<a style="'.$style.'"'.$attrs.'>';
            },
            $html,
        ) ?? $html;
    }

    private static function applyMailBodyParagraphSpacing(string $html): string
    {
        $html = preg_replace_callback(
            '/<p(\s[^>]*)?>/i',
            static fn (array $matches): string => self::mailBlockOpenTag(
                'p',
                $matches[1] ?? '',
                'margin:0 0 16px 0',
            ),
            $html,
        ) ?? $html;

        $html = preg_replace_callback(
            '/<ul(\s[^>]*)?>/i',
            static fn (array $matches): string => self::mailBlockOpenTag(
                'ul',
                $matches[1] ?? '',
                'margin:0 0 16px 0;padding-left:1.25rem',
            ),
            $html,
        ) ?? $html;

        return preg_replace_callback(
            '/<ol(\s[^>]*)?>/i',
            static fn (array $matches): string => self::mailBlockOpenTag(
                'ol',
                $matches[1] ?? '',
                'margin:0 0 16px 0;padding-left:1.25rem',
            ),
            $html,
        ) ?? $html;
    }

    /**
     * Handtekeningregels na afsluiting (Met vriendelijke groeten, …) staan in Quill vaak als aparte
     * paragrafen; 16px marge tussen elke regel oogt in mail te ruim. Voeg ze samen met <br>.
     */
    private static function compactMailSignatureSpacing(string $html): string
    {
        if (! preg_match_all('/<p(\s[^>]*)?>(.*?)<\/p>/is', $html, $matches, PREG_SET_ORDER)) {
            return $html;
        }

        $closingPattern = '/Met vriendelijke groet(en)?,?|Cordialement,?|Met de meeste hoogachting,?|Bien cordialement,?|Kind regards,?|Sincerely yours,?|Mit freundlichen Gr(?:ü|u)(?:ß|ss)en,?/iu';

        $signatureStartIndex = null;
        foreach ($matches as $index => $match) {
            $text = trim(preg_replace('/\s+/u', ' ', strip_tags($match[2])) ?? '');
            if ($text !== '' && preg_match($closingPattern, $text)) {
                $signatureStartIndex = $index;
            }
        }

        if ($signatureStartIndex === null || $signatureStartIndex >= count($matches) - 1) {
            return $html;
        }

        $result = '';
        foreach ($matches as $index => $match) {
            if ($index > $signatureStartIndex) {
                continue;
            }

            if ($index === $signatureStartIndex) {
                $result .= self::mailParagraphTag('0 0 24px 0', $match[1] ?? '', $match[2]);

                continue;
            }

            $result .= $match[0];
        }

        $signatureLines = [];
        foreach (array_slice($matches, $signatureStartIndex + 1) as $match) {
            $line = trim($match[2]);
            if ($line !== '' && ! PromoCampaignHtmlSanitizer::isBlank($line)) {
                $signatureLines[] = $line;
            }
        }

        if ($signatureLines === []) {
            return $result;
        }

        $result .= '<p style="margin:0">'.implode('<br>', $signatureLines).'</p>';

        return $result;
    }

    private static function mailParagraphTag(string $margin, string $attrs, string $inner): string
    {
        $fontSize = PromoCampaignHtmlSanitizer::fontSizeFromAttributes($attrs);
        $attrs = preg_replace('/\s+style="[^"]*"/', '', $attrs) ?? $attrs;
        $style = 'margin:'.$margin;
        if ($fontSize !== null) {
            $style .= ';font-size: '.$fontSize;
        }

        return '<p style="'.$style.'"'.$attrs.'>'.$inner.'</p>';
    }

    private static function mailBlockOpenTag(string $tag, string $attrs, string $spacingStyle): string
    {
        if (str_contains($attrs, 'margin')) {
            return '<'.$tag.$attrs.'>';
        }

        $fontSize = PromoCampaignHtmlSanitizer::fontSizeFromAttributes($attrs);
        $attrs = preg_replace('/\s+style="[^"]*"/', '', $attrs) ?? $attrs;
        $style = $spacingStyle;
        if ($fontSize !== null) {
            $style .= ';font-size: '.$fontSize;
        }

        return '<'.$tag.' style="'.$style.'"'.$attrs.'>';
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
