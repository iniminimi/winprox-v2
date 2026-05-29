<?php

namespace App\Support\Portal;

use App\Models\InternalTeam;
use App\Models\Worker;
use Illuminate\Support\Collection;

/**
 * Vaste set persoonlijke iconen voor worker-aanmelding op de werkvloer
 * (één icoon per worker per team). Geen PNG-assets: de iconen worden als
 * inline-SVG getoond via de Blade-component <x-wp-worker-icon slug="..." />.
 */
final class WorkerIcon
{
    /** @var list<string> */
    public const SLUGS = [
        'heart',
        'plane',
        'car',
        'star',
        'zap',
        'gem',
        'crown',
        'moon',
        'bell',
        'leaf',
        'key',
        'anchor',
    ];

    public static function isValidSlug(string $slug): bool
    {
        return in_array(trim($slug), self::SLUGS, true);
    }

    /** Vertaalsleutel (lang/<locale>/portal.json -> portal.worker_icon.*). */
    public static function labelKey(string $slug): string
    {
        return 'portal.worker_icon.'.trim($slug);
    }

    public static function label(string $slug): string
    {
        $slug = trim($slug);

        return self::isValidSlug($slug) ? __(self::labelKey($slug)) : $slug;
    }

    /**
     * Icoon-slugs die al door actieve workers van dit team in gebruik zijn.
     *
     * @return list<string>
     */
    public static function takenSlugsOnTeam(InternalTeam $team, ?int $exceptWorkerId = null): array
    {
        return self::workersOnTeamWithIcon($team)
            ->when($exceptWorkerId !== null, fn (Collection $workers) => $workers->where('id', '!=', $exceptWorkerId))
            ->pluck('field_icon_slug')
            ->map(fn ($slug) => trim((string) $slug))
            ->filter(fn (string $slug) => self::isValidSlug($slug))
            ->values()
            ->all();
    }

    public static function isSlugAvailableOnTeam(InternalTeam $team, string $slug, ?int $exceptWorkerId = null): bool
    {
        $slug = trim($slug);
        if (! self::isValidSlug($slug)) {
            return false;
        }

        return ! in_array($slug, self::takenSlugsOnTeam($team, $exceptWorkerId), true);
    }

    /**
     * @return Collection<int, Worker>
     */
    public static function workersOnTeamWithIcon(InternalTeam $team): Collection
    {
        return $team->workers()
            ->where('workers.is_active', true)
            ->whereIn('workers.field_icon_slug', self::SLUGS)
            ->orderBy('workers.first_name')
            ->orderBy('workers.last_name')
            ->get();
    }

    /**
     * @return array<string, Worker> slug => worker
     */
    public static function workersByIconOnTeam(InternalTeam $team): array
    {
        $map = [];

        foreach (self::workersOnTeamWithIcon($team) as $worker) {
            $slug = trim((string) $worker->field_icon_slug);
            if (self::isValidSlug($slug)) {
                $map[$slug] = $worker;
            }
        }

        return $map;
    }
}
