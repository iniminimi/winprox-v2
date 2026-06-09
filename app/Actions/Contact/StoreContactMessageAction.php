<?php

namespace App\Actions\Contact;

use App\Models\ContactMessage;
use App\Support\Tenancy;

class StoreContactMessageAction
{
    public function handle(array $data, int $tenantId): ContactMessage
    {
        Tenancy::actAs($tenantId);

        return ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'direction' => 'inbound',
        ]);
    }
}
