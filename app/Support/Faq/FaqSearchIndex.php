<?php

declare(strict_types=1);

namespace App\Support\Faq;

final class FaqSearchIndex
{
    private const RESULT_LIMIT = 5;

    /**
     * @param  list<string>  $terms
     * @return list<array{slug: string, title: string, subtitle: string}>
     */
    public static function matchingItems(array $terms): array
    {
        if ($terms === []) {
            return [];
        }

        $matches = [];

        foreach (FaqSections::orderedItems() as $item) {
            if (! is_array($item) || ! isset($item['slug'], $item['title'])) {
                continue;
            }

            $haystack = mb_strtolower(self::flattenItem($item));

            if (! self::allTermsMatch($haystack, $terms)) {
                continue;
            }

            $matches[] = [
                'slug' => (string) $item['slug'],
                'title' => (string) $item['title'],
                'subtitle' => self::subtitle($item, $terms),
            ];
        }

        return array_slice($matches, 0, self::RESULT_LIMIT);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function flattenItem(array $item): string
    {
        $parts = [];
        self::collectStrings($item, $parts);

        return implode(' ', $parts);
    }

    /**
     * @param  list<string>  $parts
     */
    private static function collectStrings(mixed $value, array &$parts): void
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '') {
                $parts[] = $trimmed;
            }

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $nested) {
            self::collectStrings($nested, $parts);
        }
    }

    /**
     * @param  list<string>  $terms
     */
    private static function allTermsMatch(string $haystack, array $terms): bool
    {
        foreach ($terms as $term) {
            if (! str_contains($haystack, mb_strtolower($term))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $terms
     */
    private static function subtitle(array $item, array $terms): string
    {
        foreach (['summary', 'intro'] as $key) {
            if (! empty($item[$key]) && is_string($item[$key])) {
                return mb_strimwidth($item[$key], 0, 90, '…');
            }
        }

        $haystack = mb_strtolower(self::flattenItem($item));
        foreach ($terms as $term) {
            $needle = mb_strtolower($term);
            $pos = mb_strpos($haystack, $needle);
            if ($pos === false) {
                continue;
            }

            $start = max(0, $pos - 20);
            $snippet = mb_substr($haystack, $start, 90);

            return ($start > 0 ? '…' : '').trim($snippet).'…';
        }

        return '';
    }
}
