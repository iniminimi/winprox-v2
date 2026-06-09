<?php

namespace App\Actions\Contact;

use App\Models\ContactMessage;
use App\Support\Tenancy;
use Illuminate\Pagination\LengthAwarePaginator;

class GetContactMessagesAction
{
    public function handle(string $filter = 'all', int $perPage = 20, ?int $tenantId = null): LengthAwarePaginator
    {
        if ($tenantId !== null) {
            Tenancy::actAs($tenantId);
        }

        $query = ContactMessage::query();

        // Only filter by tenant_id if we have a specific tenant
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if ($filter !== 'all') {
            $query->where('direction', $filter);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getUnreadCount(?int $tenantId = null): int
    {
        if ($tenantId !== null) {
            Tenancy::actAs($tenantId);
        }

        $query = ContactMessage::inbound()->unread();

        // Only filter by tenant_id if we have a specific tenant
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->count();
    }
}
