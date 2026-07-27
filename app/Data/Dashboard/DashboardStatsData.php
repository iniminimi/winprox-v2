<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

final class DashboardStatsData
{
    public function __construct(
        public int $locations,
        public int $units,
        public int $newIssues,
        public int $openTasks,
        public ?int $presentNow,
        public int $pendingReview,
        public int $timeAttention,
        public int $iotAlarms,
        public bool $hasTimeModule,
        public bool $hasIotModule,
    ) {}

    /** @return list<array{key: string, icon: string, label: string, meta: ?string, alert: bool, href_key: string}> */
    public function kpiTiles(): array
    {
        $tiles = [
            ['key' => 'locations', 'icon' => 'locations', 'label' => 'dashboard.kpi.locations', 'meta' => 'dashboard.kpi.meta_total', 'alert' => false, 'href_key' => 'locations'],
            ['key' => 'units', 'icon' => 'units', 'label' => 'dashboard.kpi.units', 'meta' => 'dashboard.kpi.meta_total', 'alert' => false, 'href_key' => 'units'],
            ['key' => 'new_issues', 'icon' => 'issues', 'label' => 'dashboard.kpi.new_issues', 'meta' => null, 'alert' => $this->newIssues > 0, 'href_key' => 'new_issues'],
            ['key' => 'open_tasks', 'icon' => 'tasks', 'label' => 'dashboard.kpi.open_tasks', 'meta' => null, 'alert' => false, 'href_key' => 'open_tasks'],
        ];

        if ($this->hasTimeModule && $this->presentNow !== null) {
            $tiles[] = ['key' => 'present_now', 'icon' => 'clock', 'label' => 'dashboard.kpi.present_now', 'meta' => null, 'alert' => false, 'href_key' => 'present_now'];
        }

        if ($this->pendingReview > 0) {
            $tiles[] = ['key' => 'pending_review', 'icon' => 'hourglass', 'label' => 'dashboard.kpi.pending_review', 'meta' => null, 'alert' => true, 'href_key' => 'pending_review'];
        }

        if ($this->hasTimeModule && $this->timeAttention > 0) {
            $tiles[] = ['key' => 'time_attention', 'icon' => 'alert-triangle', 'label' => 'dashboard.kpi.time_attention', 'meta' => null, 'alert' => true, 'href_key' => 'time_attention'];
        }

        if ($this->hasIotModule && $this->iotAlarms > 0) {
            $tiles[] = ['key' => 'iot_alarms', 'icon' => 'alert-triangle', 'label' => 'dashboard.kpi.iot_alarms', 'meta' => null, 'alert' => true, 'href_key' => 'iot_alarms'];
        }

        return $tiles;
    }

    public function valueFor(string $key): int
    {
        return match ($key) {
            'locations' => $this->locations,
            'units' => $this->units,
            'new_issues' => $this->newIssues,
            'open_tasks' => $this->openTasks,
            'present_now' => (int) $this->presentNow,
            'pending_review' => $this->pendingReview,
            'time_attention' => $this->timeAttention,
            'iot_alarms' => $this->iotAlarms,
            default => 0,
        };
    }
}
