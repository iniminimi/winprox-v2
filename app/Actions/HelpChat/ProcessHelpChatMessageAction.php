<?php

namespace App\Actions\HelpChat;

use App\Models\User;
use App\Support\HelpChat\HelpChatFaqMatcher;
use App\Support\HelpChat\HelpChatTenantInsight;
use Illuminate\Support\Facades\RateLimiter;

class ProcessHelpChatMessageAction
{
    public function __construct(
        private HelpChatFaqMatcher $matcher,
        private SaveHelpChatUnansweredQuestionAction $saveUnanswered,
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

        $this->saveUnanswered->handle($user, $locale, $trimmed);

        return [
            'role' => 'assistant',
            'content' => __('help.no_match'),
            'escalated' => true,
        ];
    }

    protected function isInsightRequest(string $message): bool
    {
        // Avoid bare "status" — that is page-help / issue status language, not tenant counts.
        $needles = [
            'mijn overzicht',
            'tenant overzicht',
            'organisatie overzicht',
            'overzicht organisatie',
            'overzicht',
            'mijn status',
            'tenant status',
            'organisatie status',
            'overview',
            'statistiek',
            'statistics',
            'zahlen',
            'aperçu',
            'apercu',
            'cómo va',
            'come va',
        ];

        $normalized = mb_strtolower($message);

        foreach ($needles as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
