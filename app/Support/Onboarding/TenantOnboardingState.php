<?php

namespace App\Support\Onboarding;

use App\Models\Category;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Unit;
use App\Models\Worker;
use Illuminate\Support\Facades\Schema;

final readonly class TenantOnboardingState
{
    public function __construct(
        public bool $needsTeamsOnboarding,
        public bool $needsCategoriesOnboarding,
        public bool $needsLocationsOnboarding,
        public bool $needsClockPointOnboarding,
        public bool $showWelcomeGuide,
    ) {}

    public static function current(): self
    {
        $teamCount = InternalTeam::query()->count();
        $workerCount = Worker::query()->count();
        $locationCount = Location::query()->count();
        $unitCount = Unit::query()->count();
        $clockPointCount = ClockPoint::query()->count();
        $categoryCount = Schema::hasTable('categories')
            ? Category::query()->count()
            : 0;

        return new self(
            needsTeamsOnboarding: $teamCount === 0,
            needsCategoriesOnboarding: $teamCount > 0 && $categoryCount === 0,
            needsLocationsOnboarding: $teamCount > 0 && ($locationCount === 0 || $unitCount === 0),
            needsClockPointOnboarding: $clockPointCount === 0,
            showWelcomeGuide: $teamCount === 0
                || $workerCount === 0
                || $locationCount === 0
                || $unitCount === 0
                || $clockPointCount === 0,
        );
    }

    public function showTeamsBanner(): bool
    {
        return $this->needsTeamsOnboarding;
    }

    public function showCategoriesBanner(): bool
    {
        return ! $this->needsTeamsOnboarding && $this->needsCategoriesOnboarding;
    }

    public function showLocationsBanner(): bool
    {
        return ! $this->needsTeamsOnboarding
            && ! $this->needsCategoriesOnboarding
            && $this->needsLocationsOnboarding;
    }

    public function showCategoriesOrLocationsBanner(): bool
    {
        return $this->showCategoriesBanner() || $this->showLocationsBanner();
    }

    public function showClockPointBanner(): bool
    {
        return ! $this->needsTeamsOnboarding
            && ! $this->needsCategoriesOnboarding
            && ! $this->needsLocationsOnboarding
            && $this->needsClockPointOnboarding;
    }

    public function blocksDashboardMain(): bool
    {
        return $this->showTeamsBanner()
            || $this->showCategoriesBanner()
            || $this->showLocationsBanner()
            || $this->showClockPointBanner();
    }
}
