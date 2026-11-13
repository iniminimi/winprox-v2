<?php

namespace App\Models\Concerns;

use App\Models\Worker;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filters en vaste volgorde voor teamdocumenten en teammededelingen in beheer.
 * Audience: 'all', 'team' (worker_id null) of een worker-id als string.
 */
trait FiltersTeamLibrary
{
    public function scopeForLibraryAudience(Builder $query, string $audience): Builder
    {
        $column = $this->qualifyColumn('worker_id');

        return match (true) {
            $audience === 'team' => $query->whereNull($column),
            ctype_digit($audience) => $query->where($column, (int) $audience),
            default => $query,
        };
    }

    public function scopeMatchingLibrarySearch(Builder $query, string $search): Builder
    {
        $term = trim($search);
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like): void {
            foreach (static::LIBRARY_SEARCH_COLUMNS as $column) {
                $q->orWhere($this->qualifyColumn($column), 'like', $like);
            }
            $q->orWhereHas('worker', fn (Builder $worker) => $worker
                ->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like));
        });
    }

    public function scopeInLibraryOrder(Builder $query): Builder
    {
        $workerId = $this->qualifyColumn('worker_id');
        $workerName = fn (string $column) => Worker::query()
            ->select($column)
            ->whereColumn('workers.id', $workerId)
            ->limit(1);

        return $query
            ->orderByRaw("CASE WHEN {$workerId} IS NULL THEN 0 ELSE 1 END")
            ->orderBy($workerName('first_name'))
            ->orderBy($workerName('last_name'))
            ->orderByDesc($this->qualifyColumn('id'));
    }
}
