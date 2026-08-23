<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Enums\PromoLanding;
use App\Models\PromoCampaignTarget;
use App\Models\PromoRecipient;

class ResolvePromoRecipientLandingAction
{
    public function handle(?PromoRecipient $recipient): PromoLanding
    {
        if ($recipient === null) {
            return PromoLanding::default();
        }

        $target = PromoCampaignTarget::query()
            ->where('promo_recipient_id', $recipient->id)
            ->with('campaign')
            ->orderByDesc('id')
            ->first();

        $landing = $target?->campaign?->landing;

        return $landing instanceof PromoLanding ? $landing : PromoLanding::default();
    }
}
