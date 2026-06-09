<?php

namespace App\Actions\Contact;

use App\Models\ContactMessage;
use App\Support\Tenancy;

class MarkContactMessageAsReadAction
{
    public function handle(ContactMessage $message, ?int $tenantId = null): void
    {
        // Security check: only verify tenant_id if we have a specific tenant
        if ($tenantId !== null && $message->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Message does not belong to the specified tenant');
        }

        if ($tenantId !== null) {
            Tenancy::actAs($tenantId);
        }

        if (!$message->isRead()) {
            $message->update(['read_at' => now()]);
        }
    }
}
