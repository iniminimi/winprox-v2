<?php

declare(strict_types=1);

namespace App\Support\Manual;

use App\Support\PageHelp;

final class ManualChapters
{
    /** @var list<string> */
    private const ADMIN_PAGE_HELP_KEYS = [
        'team.backoffice',
        'team.teams',
        'locations.categories',
        'locations.list',
        'locations.show',
        'issues.list',
        'issues.show',
        'issues.create',
        'tasks.list',
        'tasks.show',
        'calendar',
        'reservations',
        'unit-checks',
        'unit-measurements.index',
        'dashboard',
        'esg.dashboard',
        'esg.indicators',
        'esg.measurements',
        'iot.index',
        'time.presence',
        'time.shifts',
        'time.clock_points',
        'settings',
        'settings.api',
        'subscription',
    ];

    /** @var list<string> */
    private const INTERNET_PORTAL_PAGE_HELP_KEYS = [
        'portal.worker.qr',
        'portal.time',
        'portal.unit',
        'portal.team',
        'portal.worker.photos',
        'portal.teamleader.role',
        'portal.teamleader.release',
        'portal.teamleader.workers',
        'portal.teamleader.tasks',
    ];

    /**
     * @return list<string>
     */
    public static function pageHelpKeys(): array
    {
        return [...self::ADMIN_PAGE_HELP_KEYS, ...self::INTERNET_PORTAL_PAGE_HELP_KEYS];
    }

    public static function adminPageHelpKeyCount(): int
    {
        return count(self::ADMIN_PAGE_HELP_KEYS);
    }

    /**
     * @param  list<string>  $keys
     * @return list<array{key: string, title: string, actions: list<array{label: string, text: string, nested: bool}>, statuses: list<array{key: string, label: string, text: string, pill: string}>, status_note: string|null}>
     */
    public static function fromPageHelp(array $keys, bool $withoutStatuses = false): array
    {
        $chapters = [];

        foreach ($keys as $key) {
            $data = PageHelp::for($key);

            if ($data === null) {
                continue;
            }

            $data['title'] = preg_replace('/^[^\x{2014}]+\x{2014} /u', '', $data['title']);

            if ($withoutStatuses) {
                $data['statuses'] = [];
                $data['status_note'] = null;
            }

            $chapters[] = array_merge(['key' => $key], $data);
        }

        return $chapters;
    }
}
