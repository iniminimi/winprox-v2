<?php

namespace App\Actions\HelpChat;

use App\Mail\HelpChatEscalationToHelpdeskMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class EscalateHelpChatAnswerAction
{
    public function __construct(
        private SaveHelpChatUnansweredQuestionAction $saveForwarded,
    ) {}

    public function escalate(User $user, string $question, ?string $assistantReply = null): void
    {
        $this->saveForwarded->handle(
            $user,
            $user->locale ?? app()->getLocale(),
            $question,
        );

        $to = config('winprox.helpdesk_email');

        if (! $to) {
            return;
        }

        Mail::to($to)->send(new HelpChatEscalationToHelpdeskMail($user, $question, $assistantReply));
    }
}
