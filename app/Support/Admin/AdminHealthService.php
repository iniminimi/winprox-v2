<?php

namespace App\Support\Admin;

use App\Enums\AdminHealthIssueType;
use App\Models\Category;
use App\Models\Location;
use App\Models\Unit;
use Illuminate\Support\Facades\Storage;

final class AdminHealthService
{
    public function report(): AdminHealthReport
    {
        $issues = [
            ...$this->unitPhotoIssues(),
            ...$this->categoryTeamIssues(),
            ...$this->locationAddressIssues(),
        ];

        $issueCount = count($issues);
        $totalChecks = $this->totalActiveChecks();
        $completeChecks = max(0, $totalChecks - $issueCount);

        return new AdminHealthReport(
            totalChecks: $totalChecks,
            completeChecks: $completeChecks,
            issueCount: $issueCount,
            issues: $issues,
        );
    }

    private function totalActiveChecks(): int
    {
        return Unit::query()->where('is_active', true)->count()
            + Category::query()->count()
            + Location::query()->where('is_active', true)->count();
    }

    /**
     * @return list<AdminHealthIssue>
     */
    private function unitPhotoIssues(): array
    {
        $issues = [];

        $units = Unit::query()
            ->where('is_active', true)
            ->with('location:id,name')
            ->orderBy('name')
            ->get();

        foreach ($units as $unit) {
            if ($this->unitHasBackgroundPhoto($unit)) {
                continue;
            }

            $locationName = $unit->location?->name ?? __('health.no_location');

            $issues[] = new AdminHealthIssue(
                type: AdminHealthIssueType::UnitMissingPhoto,
                id: (int) $unit->id,
                title: $unit->localizedName(),
                subtitle: $locationName,
                fixUrl: route('locations.show', [
                    'location' => $unit->location_id,
                    'edit_unit' => $unit->id,
                ]),
            );
        }

        return $issues;
    }

    /**
     * @return list<AdminHealthIssue>
     */
    private function categoryTeamIssues(): array
    {
        $issues = [];

        $categories = Category::query()
            ->whereDoesntHave('teams')
            ->orderBy('name')
            ->get();

        foreach ($categories as $category) {
            $issues[] = new AdminHealthIssue(
                type: AdminHealthIssueType::CategoryMissingTeam,
                id: (int) $category->id,
                title: $category->name,
                subtitle: __('health.issue.category_team_hint'),
                fixUrl: route('locations.index', ['edit_category' => $category->id]),
            );
        }

        return $issues;
    }

    /**
     * @return list<AdminHealthIssue>
     */
    private function locationAddressIssues(): array
    {
        $issues = [];

        $locations = Location::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(static fn (Location $location): bool => ! $location->hasCompleteAddress());

        foreach ($locations as $location) {
            $issues[] = new AdminHealthIssue(
                type: AdminHealthIssueType::LocationMissingAddress,
                id: (int) $location->id,
                title: (string) ($location->name ?: __('health.unnamed_location')),
                subtitle: __('health.issue.location_address_hint'),
                fixUrl: route('locations.show', [
                    'location' => $location->id,
                    'edit' => 'location',
                ]),
            );
        }

        return $issues;
    }

    private function unitHasBackgroundPhoto(Unit $unit): bool
    {
        $path = $unit->background_photo_path;
        if (! is_string($path) || trim($path) === '') {
            return false;
        }

        return Storage::disk('public')->exists($path);
    }
}
