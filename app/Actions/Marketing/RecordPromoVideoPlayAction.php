<?php

namespace App\Actions\Marketing;

use App\Models\PromoVideoPlay;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecordPromoVideoPlayAction
{
    /**
     * @return PromoVideoPlay|null Null when already recorded for this recipient + video.
     */
    public function handle(int $promoRecipientId, string $videoKey, string $locale, CarbonInterface $playedAt): ?PromoVideoPlay
    {
        $validated = Validator::make(
            ['video_key' => $videoKey, 'locale' => $locale],
            [
                'video_key' => ['required', 'string', Rule::in(config('marketing.promo_video_keys', []))],
                'locale' => ['required', 'string', 'size:2'],
            ],
        )->validate();

        $existing = PromoVideoPlay::query()
            ->where('promo_recipient_id', $promoRecipientId)
            ->where('video_key', $validated['video_key'])
            ->first();

        if ($existing !== null) {
            return null;
        }

        try {
            return PromoVideoPlay::query()->create([
                'promo_recipient_id' => $promoRecipientId,
                'video_key' => $validated['video_key'],
                'locale' => $validated['locale'],
                'played_at' => $playedAt,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return null;
        }
    }
}
