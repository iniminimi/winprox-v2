<?php

namespace App\Actions\Contact;

use App\Models\ContactMessage;
use App\Support\Tenancy;
use Illuminate\Pagination\LengthAwarePaginator;

class GetContactMessagesAction
{
    public function handle(string $filter = 'all', int $perPage = 20, int $tenantId): LengthAwarePaginator
    {
        Tenancy::actAs($tenantId);

        $query = ContactMessage::query();

        if ($filter !== 'all') {
            $query->where('direction', $filter);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getUnreadCount(int $tenantId): int
    {
        Tenancy::actAs($tenantId);

        return ContactMessage::inbound()->unread()->count();
    }
}
