<?php

namespace App\Actions\HelpChat;

use App\Models\HelpChatUnansweredQuestion;
use App\Models\User;
use App\Support\HelpChat\HelpChatFaqMatcher;
use App\Support\HelpChat\HelpChatTenantInsight;
use Illuminate\Support\Facades\RateLimiter;

class ProcessHelpChatMessageAction
{
    public function __construct(
        private HelpChatFaqMatcher $matcher,
        private EscalateHelpChatAnswerAction $escalate,
    ) {}

    /**
     * @return array{role: string, content: string, escalated?: bool}
     */
    public function handle(User $user, string $message): array
    {
        $key = 'help-chat:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 30)) {
            return [
                'role' => 'assistant',
                'content' => __('help.rate_limited'),
            ];
        }

        RateLimiter::hit($key, 60);

        $locale = app()->getLocale();
        $trimmed = trim($message);

        if ($trimmed === '') {
            return [
                'role' => 'assistant',
                'content' => __('help.empty_message'),
            ];
        }

        if ($this->isInsightRequest($trimmed)) {
            $tenant = $user->tenant;
            if ($tenant === null) {
                return [
                    'role' => 'assistant',
                    'content' => __('help.no_tenant'),
                ];
            }

            return [
                'role' => 'assistant',
                'content' => (new HelpChatTenantInsight($tenant))->summaryLine(),
            ];
        }

        $answer = $this->matcher->match($trimmed, $locale);

        if ($answer !== null) {
            return [
                'role' => 'assistant',
                'content' => $answer,
            ];
        }

        if ($tenant = $user->tenant) {
            HelpChatUnansweredQuestion::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'locale' => $locale,
                'question' => $trimmed,
            ]);
        }

        $this->escalate->notifyHelpdesk($user, $trimmed);

        return [
            'role' => 'assistant',
            'content' => __('help.no_match'),
            'escalated' => true,
        ];
    }

    protected function isInsightRequest(string $message): bool
    {
        $needles = ['status', 'overzicht', 'overview', 'statistiek', 'statistics', 'zahlen', 'aperçu'];

        $normalized = mb_strtolower($message);

        foreach ($needles as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
