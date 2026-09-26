<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use Illuminate\Support\Collection;

/**
 * Zachte dedup-nudge: bestaande klantnamen die op de invoer lijken.
 * Geen harde blokkade — de worker/admin beslist zelf (docs/CHECKMATE.md §4.4).
 */
class SuggestCustomerNameMatchesAction
{
    private const MAX_MATCHES = 5;

    /**
     * @return Collection<int, Customer>
     */
    public function handle(int $tenantId, string $name, ?int $excludeCustomerId = null): Collection
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if (mb_strlen($normalized) < 2) {
            return collect();
        }

        $words = array_values(array_filter(
            preg_split('/\s+/', $normalized) ?: [],
            static fn (string $word): bool => mb_strlen($word) >= 2,
        ));

        if ($words === []) {
            return collect();
        }

        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->when($excludeCustomerId !== null, fn ($q) => $q->whereKeyNot($excludeCustomerId))
            ->where(function ($q) use ($normalized, $words) {
                $q->where('name', 'like', '%'.self::escapeLike($normalized).'%');
                foreach ($words as $word) {
                    $q->orWhere('name', 'like', '%'.self::escapeLike($word).'%');
                }
            })
            ->orderBy('name')
            ->limit(self::MAX_MATCHES)
            ->get();
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
