<?php

namespace App\Actions\HelpChat;

use App\Models\HelpChatUnansweredQuestion;

class DismissHelpChatUnansweredQuestionAction
{
    public function handle(int $questionId): void
    {
        HelpChatUnansweredQuestion::query()->whereKey($questionId)->delete();
    }
}
