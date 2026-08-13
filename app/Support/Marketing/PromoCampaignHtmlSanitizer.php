<?php

declare(strict_types=1);

namespace App\Support\Marketing;

final class PromoCampaignHtmlSanitizer
{
    private const ALLOWED_TAGS = 'p|br|strong|b|em|i|ul|ol|li|a|span';

    private const MIN_FONT_PX = 10;

    private const MAX_FONT_PX = 36;

    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = trim($html);
        if ($html === '') {
            return null;
        }

        $html = preg_replace('/<(?!\/?(?:'.self::ALLOWED_TAGS.')\b)[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace_callback(
            '/<(p|br|strong|b|em|i|ul|ol|li|a|span)(\s[^>]*)?\/?>/i',
            static fn (array $matches): string => self::sanitizeOpeningTag(
                strtolower($matches[1]),
                $matches[2] ?? '',
            ),
            $html,
        ) ?? $html;
        $html = self::unwrapPlainSpans($html);

        if (self::isBlank($html)) {
            return null;
        }

        return $html;
    }

    public static function forEditor(?string $html): string
    {
        return PromoCampaignQuillHtmlNormalizer::normalize($html);
    }

    public static function isBlank(?string $html): bool
    {
        if ($html === null) {
            return true;
        }

        $text = trim(preg_replace('/\s+/u', '', strip_tags($html)) ?? '');

        return $text === '';
    }

    public static function fontSizeFromAttributes(string $rawAttrs): ?string
    {
        if (preg_match('/font-size\s*:\s*(\d{1,2})px/i', $rawAttrs, $matches) === 1) {
            $px = (int) $matches[1];
            if ($px >= self::MIN_FONT_PX && $px <= self::MAX_FONT_PX) {
                return $px.'px';
            }
        }

        if (preg_match('/(?:^|\s)class="[^"]*\bql-size-(small|large|huge)\b[^"]*"/i', $rawAttrs, $matches) === 1) {
            return match (strtolower($matches[1])) {
                'small' => '12px',
                'large' => '18px',
                'huge' => '28px',
            };
        }

        return null;
    }

    private static function sanitizeOpeningTag(string $tag, string $rawAttrs): string
    {
        if ($tag === 'br') {
            return '<br/>';
        }

        $parts = [];
        if ($tag === 'a') {
            $href = self::safeHref($rawAttrs);
            if ($href !== null) {
                $parts[] = 'href="'.htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8').'"';
            }
        }

        if (in_array($tag, ['span', 'p', 'li', 'strong', 'b', 'em', 'i', 'a'], true)) {
            $fontSize = self::fontSizeFromAttributes($rawAttrs);
            if ($fontSize !== null) {
                $parts[] = 'style="font-size: '.$fontSize.'"';
            }
        }

        if ($parts === []) {
            return '<'.$tag.'>';
        }

        return '<'.$tag.' '.implode(' ', $parts).'>';
    }

    private static function safeHref(string $rawAttrs): ?string
    {
        if (preg_match('/\bhref\s*=\s*(["\'])([^"\']*)\1/i', $rawAttrs, $matches) !== 1) {
            return null;
        }

        $href = trim(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $href = urldecode($href);
        if ($href === '') {
            return null;
        }

        if (preg_match('/\{\{\s*(promo_url|welcome_url)\s*\}\}/i', $href, $placeholder) === 1) {
            return '{{'.strtolower($placeholder[1]).'}}';
        }

        if (preg_match('/^(https?:|mailto:)/i', $href) !== 1) {
            return null;
        }

        return $href;
    }

    private static function unwrapPlainSpans(string $html): string
    {
        $previous = null;
        while ($previous !== $html) {
            $previous = $html;
            $html = preg_replace('/<span>(.*?)<\/span>/is', '$1', $html) ?? $html;
        }

        return $html;
    }
}
