<?php

namespace App\Actions\Contact;

use App\Models\ContactMessage;

class ResolveContactReplyTenantAction
{
    public function handle(?ContactMessage $originalMessage): ?int
    {
        if ($originalMessage?->tenant_id !== null) {
            return (int) $originalMessage->tenant_id;
        }

        return null;
    }
}
