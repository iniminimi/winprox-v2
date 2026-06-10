<?php

namespace App\Actions\Contact;

use App\Models\ContactMessage;
use App\Support\Tenancy;

class StoreImapContactReplyAction
{
    public function handle(array $data, ?int $tenantId): ContactMessage
    {
        if ($tenantId !== null) {
            Tenancy::actAs($tenantId);
        }

        return ContactMessage::create([
            'message_id' => $data['message_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'direction' => 'inbound',
            'tenant_id' => $tenantId,
        ]);
    }
}
