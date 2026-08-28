<?php

namespace App\Support\HelpChat;

use App\Support\Manual\ManualChapters;
use App\Support\PageHelp;
use Illuminate\Support\Str;

/**
 * Match assistant questions to page-help / handleiding content (same source as ManualChapters).
 */
class HelpChatPageHelpMatcher
{
    /** @var list<string> */
    private const AMBIGUOUS_SINGLE_WORDS = [
        'status', 'app', 'help', 'hulp', 'menu', 'winprox', 'qr', 'faq',
    ];

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

            return null;
        } finally {
            if ($locale !== $previousLocale) {
                app()->setLocale($previousLocale);
            }
        }
    }

    public function searchManual(string $message, string $locale): ?string
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
            return $this->searchManualNormalized($normalized);
        } finally {
            if ($locale !== $previousLocale) {
                app()->setLocale($previousLocale);
            }
        }
    }

    protected function searchManualNormalized(string $normalized): ?string
    {
        if ($this->isAmbiguousSingleWord($normalized)) {
            return null;
        }

        $words = $this->significantWords($normalized);
        if ($words === [] && Str::length($normalized) < 5) {
            return null;
        }

        $scored = [];

        foreach ($this->manualSearchChunks() as $chunk) {
            $haystack = $this->normalize($chunk['chapter'].' '.$chunk['label'].' '.$chunk['text']);
            $score = 0;

            if (Str::length($normalized) >= 5 && Str::contains($haystack, $normalized)) {
                $score += 15;
            }

            foreach ($words as $word) {
                if (Str::contains($haystack, $word)) {
                    $score += 2;
                }
            }

            if ($score > 0) {
                $scored[] = [$score, $chunk];
            }
        }

        if ($scored === []) {
            return null;
        }

        usort($scored, function (array $a, array $b): int {
            return $b[0] <=> $a[0];
        });

        $minScore = count($words) <= 1 ? 4 : 2;
        if ($scored[0][0] < $minScore) {
            return null;
        }

        $maxActions = (int) config('help_chat_page_help.max_actions', 3);
        $picked = array_map(
            fn (array $row): array => $row[1],
            array_slice($scored, 0, $maxActions),
        );

        $lines = [];
        $currentChapter = null;

        foreach ($picked as $chunk) {
            if ($chunk['chapter'] !== $currentChapter) {
                if ($lines !== []) {
                    $lines[] = '';
                }
                $lines[] = $chunk['chapter'];
                $lines[] = '';
                $currentChapter = $chunk['chapter'];
            }
            $lines[] = $chunk['label'].': '.$this->plainText($chunk['text']);
            $lines[] = '';
        }

        $answer = trim(implode("\n", $lines));
        $maxChars = (int) config('help_chat_page_help.max_chars', 1100);

        return Str::length($answer) > $maxChars
            ? Str::limit($answer, $maxChars - 1, '…')
            : $answer;
    }

    /**
     * @return list<array{chapter: string, label: string, text: string}>
     */
    protected function manualSearchChunks(): array
    {
        $chunks = [];

        $gettingStartedTitle = __('manual.getting_started.title');
        if ($gettingStartedTitle !== 'manual.getting_started.title') {
            $chunks[] = [
                'chapter' => $gettingStartedTitle,
                'label' => $gettingStartedTitle,
                'text' => __('manual.getting_started.intro'),
            ];

            for ($i = 1; $i <= 5; $i++) {
                $stepTitle = __('manual.step_'.$i.'_title');
                if ($stepTitle === 'manual.step_'.$i.'_title') {
                    continue;
                }
                $chunks[] = [
                    'chapter' => $gettingStartedTitle,
                    'label' => $stepTitle,
                    'text' => __('manual.step_'.$i.'_text'),
                ];
            }
        }

        foreach (ManualChapters::pageHelpKeys() as $pageKey) {
            $help = PageHelp::for($pageKey);
            if ($help === null || $help['actions'] === []) {
                continue;
            }

            $chapter = $this->cleanTitle($help['title']);

            foreach ($help['actions'] as $action) {
                $chunks[] = [
                    'chapter' => $chapter,
                    'label' => $action['label'],
                    'text' => $action['text'],
                ];
            }
        }

        return $chunks;
    }

    protected function isAmbiguousSingleWord(string $normalized): bool
    {
        if (! str_contains($normalized, ' ')) {
            return in_array($normalized, self::AMBIGUOUS_SINGLE_WORDS, true);
        }

        return false;
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
