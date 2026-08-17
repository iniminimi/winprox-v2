<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Services\Billing\StripeCheckoutService;

final class BillingCatalogViewData
{
    /**
     * @return array{
     *     planKeys: list<string>,
     *     stripeReadyPlans: list<string>,
     *     stripeLive: bool,
     * }
     */
    public static function catalog(): array
    {
        $planKeys = self::publicPlanKeys();

        $stripeService = app(StripeCheckoutService::class);
        $stripeReadyPlans = collect($planKeys)
            ->merge(self::timeVariantKeys())
            ->unique()
            ->filter(fn (string $key) => $stripeService->isConfiguredForPlan($key))
            ->values()
            ->all();

        $stripeLive = config('stripe.enabled') && $stripeReadyPlans !== [];

        return [
            'planKeys' => $planKeys,
            'stripeReadyPlans' => $stripeReadyPlans,
            'stripeLive' => $stripeLive,
        ];
    }

    /**
     * @return list<string>
     */
    public static function publicPlanKeys(): array
    {
        $keys = [];

        foreach (config('billing.plans', []) as $key => $row) {
            if (is_array($row) && ($row['public_catalog'] ?? false) === true) {
                $keys[] = (string) $key;
            }
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    public static function timeVariantKeys(): array
    {
        $keys = [];

        foreach (config('billing.plans', []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $variant = $row['time_variant'] ?? null;
            if (is_string($variant) && $variant !== '') {
                $keys[] = $variant;
            }
        }

        return $keys;
    }

    public static function activationPlan(string $catalogPlan, bool $includeTime): string
    {
        if (! $includeTime) {
            return $catalogPlan;
        }

        $variant = config("billing.plans.{$catalogPlan}.time_variant");

        return is_string($variant) && $variant !== '' ? $variant : $catalogPlan;
    }

    public static function catalogPlanFor(string $planKey): string
    {
        foreach (config('billing.plans', []) as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            if (($row['time_variant'] ?? null) === $planKey) {
                return (string) $key;
            }
        }

        return $planKey;
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultTimeToggles(?string $selectedPlan): array
    {
        $toggles = [];

        foreach (config('billing.plans', []) as $key => $row) {
            if (! is_array($row) || ($row['public_catalog'] ?? false) !== true) {
                continue;
            }

            $variant = $row['time_variant'] ?? null;
            $toggles[(string) $key] = is_string($variant) && $variant !== '' && $selectedPlan === $variant;
        }

        return $toggles;
    }
}
