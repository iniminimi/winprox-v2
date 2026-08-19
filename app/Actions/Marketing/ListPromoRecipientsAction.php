<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Models\PromoRecipient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Bestemmelingen voor het platformoverzicht. Elke promo-mail maakt een bestemmeling aan voor
 * de persoonlijke trackinglink, dus deze lijst groeit met de campagnes mee: altijd pagineren.
 */
class ListPromoRecipientsAction
{
    public function handle(string $search = '', int $page = 1, int $perPage = 25): LengthAwarePaginator
    {
        $search = trim($search);

        return PromoRecipient::query()
            ->withCount(['visits', 'videoPlays'])
            ->with(['videoPlays', 'latestSentEmailSend', 'latestEmailSendAttempt'])
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function ($subQuery) use ($like): void {
                    $subQuery->where('label', 'like', $like)
                        ->orWhere('note', 'like', $like)
                        ->orWhere('token', 'like', $like);
                });
            })
            ->latest('id')
            ->paginate(perPage: $perPage, page: $page);
    }
}
