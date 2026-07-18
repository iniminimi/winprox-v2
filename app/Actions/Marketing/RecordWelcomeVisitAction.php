<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Models\WelcomeVisit;
use App\Support\Marketing\PromoVisitScannerDetector;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RecordWelcomeVisitAction
{
    /**
     * Log at most one unique welcome visit per visitor hash per calendar day.
     *
     * @return WelcomeVisit|null Null when skipped (bot, empty IP, or already counted today).
     */
    public function handle(
        string $locale,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $utmSource = null,
        ?string $utmMedium = null,
        ?string $utmCampaign = null,
        ?CarbonInterface $visitedAt = null,
    ): ?WelcomeVisit {
        if (PromoVisitScannerDetector::isAutomatedFetch($userAgent)) {
            return null;
        }

        $ipAddress = trim((string) $ipAddress);
        if ($ipAddress === '') {
            return null;
        }

        $visitedAt = Carbon::parse($visitedAt ?? now())->timezone(config('app.timezone'));
        $visitorHash = hash_hmac('sha256', $ipAddress, (string) config('app.key'));
        $dayStart = $visitedAt->copy()->startOfDay();
        $dayEnd = $visitedAt->copy()->endOfDay();

        $alreadyCounted = WelcomeVisit::query()
            ->where('visitor_hash', $visitorHash)
            ->whereBetween('visited_at', [$dayStart, $dayEnd])
            ->exists();

        if ($alreadyCounted) {
            return null;
        }

        return WelcomeVisit::query()->create([
            'visited_at' => $visitedAt,
            'locale' => Str::lower(Str::substr(trim($locale), 0, 5)) ?: 'nl',
            'visitor_hash' => $visitorHash,
            'utm_source' => $this->nullableTruncated($utmSource, 64),
            'utm_medium' => $this->nullableTruncated($utmMedium, 64),
            'utm_campaign' => $this->nullableTruncated($utmCampaign, 128),
        ]);
    }

    private function nullableTruncated(?string $value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return Str::substr($value, 0, $max);
    }
}
