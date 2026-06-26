<?php

namespace App\Support\Admin;

use App\Enums\AdminHealthIssueType;
use App\Models\Category;
use App\Models\Document;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Unit;
use App\Models\Worker;
use Illuminate\Support\Facades\Storage;

final class AdminHealthService
{
    public function issueCount(): int
    {
        return count($this->collectIssues());
    }

    public function report(): AdminHealthReport
    {
        $issues = $this->collectIssues();
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

    public function summary(): AdminConfigSummary
    {
        return new AdminConfigSummary(
            report: $this->report(),
            inactiveLocationCount: Location::query()->where('is_active', false)->count(),
            inactiveUnitCount: Unit::query()->where('is_active', false)->count(),
            inactiveTeamCount: InternalTeam::query()->where('is_active', false)->count(),
            inactiveWorkerCount: Worker::query()->where('is_active', false)->count(),
            categoryGpsEnabledCount: Category::query()->where('allow_gps_location', true)->count(),
            categoryGpsDisabledCount: Category::query()->where('allow_gps_location', false)->count(),
            inactiveDocumentCount: Document::query()->where('is_active', false)->count(),
            activeDocumentCount: Document::query()->where('is_active', true)->count(),
        );
    }

    /**
     * @return list<AdminHealthIssue>
     */
    private function collectIssues(): array
    {
        return [
            ...$this->unitPhotoIssues(),
            ...$this->unitMissingGpsIssues(),
            ...$this->unitPublicReportsDisabledIssues(),
            ...$this->inactiveDocumentIssues(),
            ...$this->categoryTeamIssues(),
            ...$this->locationAddressIssues(),
        ];
    }

    private function totalActiveChecks(): int
    {
        $activeUnits = Unit::query()->where('is_active', true)->count();
        $gpsEligibleUnits = Unit::query()
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('allow_gps_location', true))
            ->count();
        $documentsOnActiveLocations = Document::query()
            ->whereHas('location', fn ($query) => $query->where('is_active', true))
            ->count();

        return $activeUnits
            + $gpsEligibleUnits
            + $activeUnits
            + $documentsOnActiveLocations
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

            $issues[] = new AdminHealthIssue(
                type: AdminHealthIssueType::UnitMissingPhoto,
                id: (int) $unit->id,
                title: $unit->localizedName(),
                subtitle: $unit->location?->localizedName() ?? __('health.no_location'),
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
    private function unitMissingGpsIssues(): array
    {
        $issues = [];

        $units = Unit::query()
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('allow_gps_location', true))
            ->whereDoesntHave('gpsReports')
            ->with(['location:id,name', 'category:id,name'])
            ->orderBy('name')
            ->get();

        foreach ($units as $unit) {
            $issues[] = new AdminHealthIssue(
                type: AdminHealthIssueType::UnitMissingGps,
                id: (int) $unit->id,
                title: $unit->localizedName(),
                subtitle: $unit->location?->localizedName() ?? __('health.no_location'),
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
    private function unitPublicReportsDisabledIssues(): array
    {
        $issues = [];

        $units = Unit::query()
            ->where('is_active', true)
            ->where('public_reports_enabled', false)
            ->with('location:id,name')
            ->orderBy('name')
            ->get();

        foreach ($units as $unit) {
            $issues[] = new AdminHealthIssue(
                type: AdminHealthIssueType::UnitPublicReportsDisabled,
                id: (int) $unit->id,
                title: $unit->localizedName(),
                subtitle: $unit->location?->localizedName() ?? __('health.no_location'),
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
    private function inactiveDocumentIssues(): array
    {
        $issues = [];

        $documents = Document::query()
            ->where('is_active', false)
            ->whereHas('location', fn ($query) => $query->where('is_active', true))
            ->with(['location:id,name', 'unit:id,name,location_id'])
            ->orderBy('description')
            ->get();

        foreach ($documents as $document) {
            $unitLabel = $document->unit?->localizedName() ?? __('locations.documents.for_location');

            $issues[] = new AdminHealthIssue(
                type: AdminHealthIssueType::InactiveDocument,
                id: (int) $document->id,
                title: $document->localizedDescription() ?: $document->title,
                subtitle: trim(($document->location?->localizedName() ?? '').' · '.$unitLabel, ' ·'),
                fixUrl: route('locations.show', ['location' => $document->location_id]),
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
                title: (string) ($location->localizedName() ?: __('health.unnamed_location')),
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
