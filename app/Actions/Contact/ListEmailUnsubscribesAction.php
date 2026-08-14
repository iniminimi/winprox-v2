<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Enums\EmailUnsubscribeSource;
use App\Models\EmailUnsubscribe;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ListEmailUnsubscribesAction
{
    /**
     * @return array{
     *     rows: LengthAwarePaginator,
     *     matchedUsers: Collection<string, User>,
     *     voluntaryCount: int,
     *     undeliverableCount: int,
     *     manualCount: int
     * }
     */
    public function handle(
        string $search = '',
        int $page = 1,
        int $perPage = 50,
        bool $voluntaryOnly = false,
        bool $undeliverableOnly = false,
        bool $manualOnly = false,
    ): array {
        $search = trim($search);

        $sourceFilters = [];
        if ($voluntaryOnly) {
            $sourceFilters[] = EmailUnsubscribeSource::Voluntary;
        }
        if ($undeliverableOnly) {
            $sourceFilters[] = EmailUnsubscribeSource::Undeliverable;
        }
        if ($manualOnly) {
            $sourceFilters[] = EmailUnsubscribeSource::Manual;
        }

        $rows = EmailUnsubscribe::query()
            ->when($sourceFilters !== [], function ($query) use ($sourceFilters): void {
                $query->whereIn('source', $sourceFilters);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where('email', 'like', $like);
            })
            ->orderByDesc('unsubscribed_at')
            ->paginate(perPage: $perPage, page: $page);

        return [
            'rows' => $rows,
            'matchedUsers' => $this->matchedUsers($rows->getCollection()),
            'voluntaryCount' => EmailUnsubscribe::query()
                ->where('source', EmailUnsubscribeSource::Voluntary)
                ->count(),
            'undeliverableCount' => EmailUnsubscribe::query()
                ->where('source', EmailUnsubscribeSource::Undeliverable)
                ->count(),
            'manualCount' => EmailUnsubscribe::query()
                ->where('source', EmailUnsubscribeSource::Manual)
                ->count(),
        ];
    }

    /**
     * @param  Collection<int, EmailUnsubscribe>  $rows
     * @return Collection<string, User>
     */
    private function matchedUsers(Collection $rows): Collection
    {
        $emails = $rows
            ->pluck('email')
            ->map(static fn (string $email): string => EmailUnsubscribe::normalizeEmail($email))
            ->unique()
            ->values()
            ->all();

        if ($emails === []) {
            return collect();
        }

        return User::query()
            ->with('tenant')
            ->whereIn('email', $emails)
            ->get()
            ->keyBy(static fn (User $user): string => EmailUnsubscribe::normalizeEmail((string) $user->email));
    }
}
