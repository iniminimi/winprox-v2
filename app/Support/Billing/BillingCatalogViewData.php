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
        $planKeys = array_keys(config('billing.plans', []));

        $stripeService = app(StripeCheckoutService::class);
        $stripeReadyPlans = collect($planKeys)
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
}
