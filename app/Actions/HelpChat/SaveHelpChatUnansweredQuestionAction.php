<?php

namespace App\Actions\HelpChat;

use App\Models\HelpChatUnansweredQuestion;
use App\Models\Tenant;
use App\Models\User;

class SaveHelpChatUnansweredQuestionAction
{
    public function handle(User $user, string $locale, string $question): ?HelpChatUnansweredQuestion
    {
        $tenant = $user->tenant;
        if (! $tenant instanceof Tenant) {
            return null;
        }

        return HelpChatUnansweredQuestion::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'locale' => $locale,
            'question' => $question,
        ]);
    }
}
