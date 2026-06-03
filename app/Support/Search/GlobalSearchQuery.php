<?php

declare(strict_types=1);

namespace App\Support\Search;

use Illuminate\Database\Eloquent\Builder;

final class GlobalSearchQuery
{
    /**
     * @param  list<string>  $terms
     */
    public static function applyAllTerms(Builder $query, array $terms, callable $matchTerm): void
    {
        foreach ($terms as $term) {
            $query->where(static function (Builder $termQuery) use ($matchTerm, $term): void {
                $matchTerm($termQuery, $term);
            });
        }
    }

    public static function likeTerm(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }

    public static function applyWorkerNameMatch(Builder $query, string $term): void
    {
        $like = self::likeTerm($term);

        $query->where(static function (Builder $nameQuery) use ($like): void {
            $nameQuery->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like);

            if ($nameQuery->getConnection()->getDriverName() === 'mysql') {
                $nameQuery->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]);
            } else {
                $nameQuery->orWhereRaw("(first_name || ' ' || last_name) LIKE ?", [$like]);
            }
        });
    }

    /**
     * @param  list<string>  $columns
     */
    public static function applyColumnLike(Builder $query, string $term, array $columns): void
    {
        $like = self::likeTerm($term);

        $query->where(static function (Builder $columnQuery) use ($columns, $like): void {
            foreach ($columns as $column) {
                $columnQuery->orWhere($column, 'like', $like);
            }
        });
    }
}
