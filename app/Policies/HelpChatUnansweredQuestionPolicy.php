<?php

namespace App\Policies;

use App\Models\HelpChatUnansweredQuestion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class HelpChatUnansweredQuestionPolicy
{
    public function delete(User $user, HelpChatUnansweredQuestion $question): Response
    {
        return $user->is_superuser
            ? Response::allow()
            : Response::deny('Only superusers can dismiss unanswered help questions.');
    }
}
