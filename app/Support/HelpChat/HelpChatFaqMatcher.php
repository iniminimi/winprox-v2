<?php

namespace App\Support\HelpChat;

use App\Enums\HelpChatKnowledgeBaseEntryTranslationStatus;
use App\Models\HelpChatKnowledgeBaseEntry;
use App\Models\HelpChatKnowledgeBaseEntryTranslation;
use App\Support\Translation\LocaleSupport;
use Illuminate\Support\Str;

class HelpChatFaqMatcher
{
    /**
     * Woorden korter dan dit matchen niet los uit een lange kennisbankzin.
     * «inchecken» (9) wel, «niet» of «locatie» niet.
     */
    private const PATTERN_KEYWORD_MIN_LENGTH = 8;

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

        $faqAnswer = $this->matchConfigFaq($normalized, $locale);
        if ($faqAnswer !== null) {
            return $faqAnswer;
        }

        return $this->pageHelpMatcher->searchManual($message, $locale);
    }

    protected function matchKnowledgeBase(string $normalized, string $locale): ?string
    {
        $locale = LocaleSupport::normalize($locale);

        $bestAnswer = null;
        $bestScore = 0;

        $sourceEntries = HelpChatKnowledgeBaseEntry::query()
            ->where('is_active', true)
            ->where('original_language', $locale)
            ->get();

        foreach ($sourceEntries as $entry) {
            $score = $this->knowledgeBaseScore($normalized, $entry->patterns ?? []);
            if ($score > $bestScore && $entry->answer !== '') {
                $bestScore = $score;
                $bestAnswer = $entry->answer;
            }
        }

        $translations = HelpChatKnowledgeBaseEntryTranslation::query()
            ->where('locale', $locale)
            ->where('status', HelpChatKnowledgeBaseEntryTranslationStatus::Completed)
            ->whereHas('entry', fn ($query) => $query->where('is_active', true))
            ->get();

        foreach ($translations as $translation) {
            $score = $this->knowledgeBaseScore($normalized, $translation->patterns ?? []);
            if ($score > $bestScore && filled($translation->answer)) {
                $bestScore = $score;
                $bestAnswer = $translation->answer;
            }
        }

        return $bestAnswer;
    }

    /**
     * @param  list<string>  $patterns
     */
    protected function knowledgeBaseScore(string $normalized, array $patterns): int
    {
        $best = 0;

        foreach ($patterns as $pattern) {
            $pattern = $this->normalize((string) $pattern);

            if ($pattern === '') {
                continue;
            }

            if (Str::contains($normalized, $pattern)) {
                $best = max($best, 1000 + Str::length($pattern));

                continue;
            }

            foreach (preg_split('/\s+/', $pattern) ?: [] as $word) {
                $word = preg_replace('/[^\p{L}\p{N}]/u', '', (string) $word) ?? '';

                if (Str::length($word) < self::PATTERN_KEYWORD_MIN_LENGTH) {
                    continue;
                }

                if (Str::contains($normalized, $word)) {
                    $best = max($best, Str::length($word));
                }
            }
        }

        return $best;
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
