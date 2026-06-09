<?php

namespace App\Actions\Contact;

use App\Models\ContactMessage;
use App\Support\Tenancy;

class MarkContactMessageAsReadAction
{
    public function handle(ContactMessage $contactMessage, int $tenantId): ContactMessage
    {
        Tenancy::actAs($tenantId);

        if (!$contactMessage->isRead()) {
            $contactMessage->update(['read_at' => now()]);
        }

        return $contactMessage;
    }
}
