<?php

namespace App\Policies;

use App\Models\HelpChatKnowledgeBaseEntry;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class HelpChatKnowledgeBaseEntryPolicy
{
    public function viewAny(User $user): Response
    {
        return $user->is_superuser
            ? Response::allow()
            : Response::deny('Only superusers can manage the help knowledge base.');
    }

    public function create(User $user): Response
    {
        return $this->viewAny($user);
    }

    public function update(User $user, HelpChatKnowledgeBaseEntry $entry): Response
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, HelpChatKnowledgeBaseEntry $entry): Response
    {
        return $this->viewAny($user);
    }
}
