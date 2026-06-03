<?php

declare(strict_types=1);

namespace App\Support\Search;

final class GlobalSearchTerms
{
    /**
     * @return list<string>
     */
    public static function fromQuery(string $query, int $minLength = 2): array
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
        if ($normalized === '') {
            return [];
        }

        $terms = preg_split('/\s+/u', $normalized) ?: [];
        $terms = array_values(array_unique(array_filter(
            $terms,
            static fn (string $term): bool => mb_strlen($term) >= $minLength,
        )));

        if ($terms === [] && mb_strlen($normalized) >= $minLength) {
            return [$normalized];
        }

        return $terms;
    }
}
