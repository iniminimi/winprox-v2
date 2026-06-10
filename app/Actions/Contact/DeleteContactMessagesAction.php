<?php

namespace App\Actions\Contact;

use App\Models\ContactMessage;

class DeleteContactMessagesAction
{
    /**
     * @param  list<int>  $messageIds
     */
    public function handle(array $messageIds): int
    {
        if ($messageIds === []) {
            return 0;
        }

        return ContactMessage::query()->whereIn('id', $messageIds)->delete();
    }
}
