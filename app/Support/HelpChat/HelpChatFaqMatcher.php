<?php

namespace App\Support\HelpChat;

use App\Models\HelpChatKnowledgeBaseEntry;
use Illuminate\Support\Str;

class HelpChatFaqMatcher
{
    public function __construct(
        private HelpChatPageHelpMatcher $pageHelpMatcher,
    ) {}

    public function match(string $message, string $locale): ?string
    {
        $normalized = $this->normalize($message);

        if ($normalized === '') {
            return null;
        }

        $kbAnswer = $this->matchKnowledgeBase($normalized, $locale);
        if ($kbAnswer !== null) {
            return $kbAnswer;
        }

        // Prefer page-help / handleiding (richer how-to) over short FAQ summaries.
        $pageHelpAnswer = $this->pageHelpMatcher->match($message, $locale);
        if ($pageHelpAnswer !== null) {
            return $pageHelpAnswer;
        }

        return $this->matchConfigFaq($normalized, $locale);
    }

    protected function matchKnowledgeBase(string $normalized, string $locale): ?string
    {
        $entries = HelpChatKnowledgeBaseEntry::query()
            ->where('is_active', true)
            ->where(function ($query) use ($locale) {
                $query->where('locale', $locale)->orWhere('locale', '*');
            })
            ->get();

        foreach ($entries as $entry) {
            foreach ($entry->patterns as $pattern) {
                if ($this->patternMatches($normalized, (string) $pattern)) {
                    return $entry->answer;
                }
            }
        }

        return null;
    }

    protected function matchConfigFaq(string $normalized, string $locale): ?string
    {
        $entries = config('help_chat_faq.entries', []);

        foreach ($entries as $entry) {
            $patterns = $entry['patterns'] ?? [];
            foreach ($patterns as $pattern) {
                if ($this->patternMatches($normalized, (string) $pattern)) {
                    $bodyKey = $entry['body_key'] ?? null;
                    if ($bodyKey) {
                        return __($bodyKey);
                    }
                }
            }
        }

        return null;
    }

    protected function patternMatches(string $normalized, string $pattern): bool
    {
        $pattern = $this->normalize($pattern);

        if ($pattern === '') {
            return false;
        }

        if (Str::contains($normalized, $pattern)) {
            return true;
        }

        return false;
    }

    protected function normalize(string $value): string
    {
        return Str::lower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }
}
