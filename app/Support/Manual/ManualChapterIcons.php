<?php

declare(strict_types=1);

namespace App\Support\Manual;

final class ManualChapterIcons
{
    /** @var array<string, string> */
    private const ICONS = [
        'team' => 'team',
        'locations.list' => 'locations',
        'locations.show' => 'locations',
        'issues.list' => 'issues',
        'issues.show' => 'issues',
        'issues.create' => 'issues',
        'tasks.list' => 'tasks',
        'tasks.show' => 'tasks',
        'calendar' => 'calendar',
        'dashboard' => 'dashboard',
        'esg.indicators' => 'document',
        'esg.measurements' => 'sliders',
        'time.presence' => 'clock',
        'time.shifts' => 'calendar',
        'time.clock_points' => 'map-pin',
        'settings' => 'settings',
        'settings.api' => 'api',
        'statuses.admin-portal' => 'dashboard',
        'statuses.internet-portal' => 'map-pin',
        'portal.worker.qr' => 'map-pin',
        'portal.time' => 'clock',
        'portal.unit' => 'eye',
        'portal.team' => 'team',
        'portal.worker.photos' => 'document',
        'portal.teamleader.role' => 'team',
        'portal.teamleader.release' => 'team',
        'portal.teamleader.workers' => 'team',
        'portal.teamleader.tasks' => 'tasks',
    ];

    public static function for(string $chapterKey): ?string
    {
        return self::ICONS[$chapterKey] ?? null;
    }

    /**
     * @param  list<array{key: string, title: string, actions: list<mixed>, statuses: list<mixed>, status_note: string|null}>  $chapters
     * @return list<array{key: string, title: string, actions: list<mixed>, statuses: list<mixed>, status_note: string|null, icon: string|null}>
     */
    public static function applyToChapters(array $chapters): array
    {
        return array_map(static function (array $chapter): array {
            $chapter['icon'] = self::for($chapter['key']);

            return $chapter;
        }, $chapters);
    }
}
