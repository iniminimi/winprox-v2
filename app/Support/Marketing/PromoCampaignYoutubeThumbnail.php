<?php

declare(strict_types=1);

namespace App\Support\Marketing;

final class PromoCampaignYoutubeThumbnail
{
    public const PLACEHOLDER = '{{youtube_thumbnail}}';

    public static function expandInMailHtml(string $html, ?string $youtubeUrl = null): string
    {
        if ($html === '') {
            return '';
        }

        $html = self::replaceThumbnailPlaceholder($html, $youtubeUrl);
        $html = self::expandYoutubeAnchorParagraphs($html);

        return $html;
    }

    public static function extractVideoId(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (! str_contains($url, '://')) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return null;
        }

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');

        if ($host === 'youtu.be') {
            $id = explode('/', $path)[0] ?? '';

            return self::normalizeVideoId($id);
        }

        if (! in_array($host, ['youtube.com', 'm.youtube.com', 'music.youtube.com'], true)) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        if (isset($query['v'])) {
            return self::normalizeVideoId((string) $query['v']);
        }

        foreach (['embed', 'shorts', 'live'] as $segment) {
            if (str_starts_with($path, $segment.'/')) {
                $id = substr($path, strlen($segment) + 1);

                return self::normalizeVideoId(explode('/', $id)[0] ?? '');
            }
        }

        return null;
    }

    public static function watchUrl(string $videoId): string
    {
        return 'https://www.youtube.com/watch?v='.$videoId;
    }

    public static function thumbnailUrl(string $videoId): string
    {
        return 'https://img.youtube.com/vi/'.$videoId.'/hqdefault.jpg';
    }

    private static function replaceThumbnailPlaceholder(string $html, ?string $youtubeUrl): string
    {
        if (! str_contains($html, self::PLACEHOLDER)) {
            return $html;
        }

        $videoId = self::extractVideoId((string) $youtubeUrl);
        if ($videoId === null) {
            return str_replace(self::PLACEHOLDER, '', $html);
        }

        $block = self::thumbnailParagraph($videoId);

        $html = preg_replace(
            '/<p[^>]*>\s*'.preg_quote(self::PLACEHOLDER, '/').'\s*<\/p>/',
            $block,
            $html,
        ) ?? $html;

        return str_replace(self::PLACEHOLDER, $block, $html);
    }

    private static function expandYoutubeAnchorParagraphs(string $html): string
    {
        return preg_replace_callback(
            '/<p([^>]*)>\s*<a\s+([^>]*?)href="([^"]+)"([^>]*)>.*?<\/a>\s*<\/p>/is',
            static function (array $matches): string {
                $href = html_entity_decode($matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $videoId = self::extractVideoId($href);
                if ($videoId === null) {
                    return $matches[0];
                }

                return self::thumbnailParagraph($videoId);
            },
            $html,
        ) ?? $html;
    }

    private static function thumbnailParagraph(string $videoId): string
    {
        $watchUrl = htmlspecialchars(self::watchUrl($videoId), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $thumbnailUrl = htmlspecialchars(self::thumbnailUrl($videoId), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<p style="margin:0 0 16px 0;text-align:center">'
            .'<a href="'.$watchUrl.'" style="display:inline-block;text-decoration:none">'
            .'<img src="'.$thumbnailUrl.'" alt="YouTube" width="320" '
            .'style="display:block;max-width:100%;height:auto;border:0;border-radius:8px" />'
            .'</a></p>';
    }

    private static function normalizeVideoId(string $id): ?string
    {
        $id = trim($id);
        if ($id === '' || ! preg_match('/^[A-Za-z0-9_-]{6,}$/', $id)) {
            return null;
        }

        return $id;
    }
}
