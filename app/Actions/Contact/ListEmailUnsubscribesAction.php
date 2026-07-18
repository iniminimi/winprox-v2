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
     *     undeliverableCount: int
     * }
     */
    public function handle(
        string $search = '',
        int $page = 1,
        int $perPage = 50,
        bool $undeliverableOnly = false,
    ): array {
        $search = trim($search);

        $rows = EmailUnsubscribe::query()
            ->when(
                $undeliverableOnly,
                function ($query): void {
                    $query->where('source', EmailUnsubscribeSource::Undeliverable);
                },
                function ($query): void {
                    $query->where('source', '!=', EmailUnsubscribeSource::Undeliverable);
                },
            )
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where('email', 'like', $like);
            })
            ->orderByDesc('unsubscribed_at')
            ->paginate(perPage: $perPage, page: $page);

        return [
            'rows' => $rows,
            'matchedUsers' => $this->matchedUsers($rows->getCollection()),
            'undeliverableCount' => EmailUnsubscribe::query()
                ->where('source', EmailUnsubscribeSource::Undeliverable)
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
