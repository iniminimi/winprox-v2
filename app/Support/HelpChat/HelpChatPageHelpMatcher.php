<?php

namespace App\Support\HelpChat;

use App\Support\PageHelp;
use Illuminate\Support\Str;

/**
 * Match assistant questions to page-help / handleiding content (same source as ManualChapters).
 */
class HelpChatPageHelpMatcher
{
    public function match(string $message, string $locale): ?string
    {
        $normalized = $this->normalize($message);

        if ($normalized === '') {
            return null;
        }

        $previousLocale = app()->getLocale();
        if ($locale !== $previousLocale) {
            app()->setLocale($locale);
        }

        try {
            foreach (config('help_chat_page_help.entries', []) as $entry) {
                $patterns = $entry['patterns'] ?? [];
                foreach ($patterns as $pattern) {
                    if (! $this->patternMatches($normalized, (string) $pattern)) {
                        continue;
                    }

                    if (($entry['type'] ?? null) === 'manual_getting_started') {
                        return $this->formatGettingStarted();
                    }

                    $page = $entry['page'] ?? null;
                    if (! is_string($page) || $page === '') {
                        continue;
                    }

                    $prefer = $entry['prefer'] ?? [];
                    if (! is_array($prefer)) {
                        $prefer = [];
                    }

                    $answer = $this->formatPageAnswer($page, $normalized, $prefer);
                    if ($answer !== null) {
                        return $answer;
                    }
                }
            }
        } finally {
            if ($locale !== $previousLocale) {
                app()->setLocale($previousLocale);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $prefer
     */
    protected function formatPageAnswer(string $pageKey, string $normalized, array $prefer): ?string
    {
        $help = PageHelp::for($pageKey);

        if ($help === null) {
            return null;
        }

        $actions = $help['actions'];
        if ($actions === []) {
            return null;
        }

        $scored = [];
        foreach ($actions as $index => $action) {
            $haystack = $this->normalize($action['label'].' '.$this->plainText($action['text']));
            $score = 0;

            foreach ($prefer as $needle) {
                $needle = $this->normalize((string) $needle);
                if ($needle !== '' && Str::contains($haystack, $needle)) {
                    $score += 10;
                }
            }

            foreach ($this->significantWords($normalized) as $word) {
                if (Str::contains($haystack, $word)) {
                    $score += 2;
                }
            }

            // Mild preference for earlier actions when scores tie.
            $scored[] = [$score, -$index, $action];
        }

        usort($scored, function (array $a, array $b): int {
            return [$b[0], $b[1]] <=> [$a[0], $a[1]];
        });

        $maxActions = (int) config('help_chat_page_help.max_actions', 3);
        $picked = [];
        foreach ($scored as [$score, , $action]) {
            if ($score <= 0 && $picked !== []) {
                break;
            }
            $picked[] = $action;
            if (count($picked) >= $maxActions) {
                break;
            }
        }

        if ($picked === []) {
            $picked = array_slice($actions, 0, $maxActions);
        }

        $title = $this->cleanTitle($help['title']);
        $lines = [$title, ''];

        foreach ($picked as $action) {
            $lines[] = $action['label'].': '.$this->plainText($action['text']);
            $lines[] = '';
        }

        $answer = trim(implode("\n", $lines));
        $maxChars = (int) config('help_chat_page_help.max_chars', 1100);

        if (Str::length($answer) > $maxChars) {
            $answer = Str::limit($answer, $maxChars - 1, '…');
        }

        return $answer;
    }

    protected function formatGettingStarted(): ?string
    {
        $lines = [
            __('manual.getting_started.title'),
            '',
            __('manual.getting_started.intro'),
            '',
        ];

        for ($i = 1; $i <= 5; $i++) {
            $title = __('manual.step_'.$i.'_title');
            $text = __('manual.step_'.$i.'_text');
            if ($title === 'manual.step_'.$i.'_title') {
                continue;
            }
            $lines[] = $i.'. '.$title;
            $lines[] = $this->plainText($text);
            $lines[] = '';
        }

        $answer = trim(implode("\n", $lines));
        if ($answer === '') {
            return null;
        }

        $maxChars = (int) config('help_chat_page_help.max_chars', 1100);

        return Str::length($answer) > $maxChars
            ? Str::limit($answer, $maxChars - 1, '…')
            : $answer;
    }

    protected function cleanTitle(string $title): string
    {
        $cleaned = preg_replace('/^[^\x{2014}]+\x{2014}\s*/u', '', $title) ?? $title;

        return trim($cleaned);
    }

    protected function plainText(string $value): string
    {
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    /**
     * @return list<string>
     */
    protected function significantWords(string $normalized): array
    {
        $parts = preg_split('/[^a-z0-9àáâäãåāæçèéêëēìíîïīñòóôöõøōœùúûüūýÿß]+/u', $normalized) ?: [];
        $words = [];

        foreach ($parts as $part) {
            if (Str::length($part) < 4) {
                continue;
            }
            // Skip very common Dutch/English filler that adds noise.
            if (in_array($part, ['voor', 'naar', 'met', 'een', 'van', 'het', 'that', 'this', 'with', 'from', 'have', 'will'], true)) {
                continue;
            }
            $words[] = $part;
        }

        return array_values(array_unique($words));
    }

    protected function patternMatches(string $normalized, string $pattern): bool
    {
        $pattern = $this->normalize($pattern);

        return $pattern !== '' && Str::contains($normalized, $pattern);
    }

    protected function normalize(string $value): string
    {
        return Str::lower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }
}
