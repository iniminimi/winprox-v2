<?php

declare(strict_types=1);

namespace App\Support\Manual;

use App\Enums\TaskStatus;

final class StructuredManual
{
    /**
     * @return array<int, array{key: string, title: string, actions: array<int, array{label: string, text: string, nested?: bool}>, statuses: array<int, array{key: string, label: string, text: string, pill: string}>, status_note: ?string}>
     */
    public static function chapters(string $translationRoot): array
    {
        $raw = __("{$translationRoot}.chapters");

        if (! is_array($raw)) {
            return [];
        }

        $chapters = [];
        $index = 0;

        foreach ($raw as $chapter) {
            if (! is_array($chapter)) {
                continue;
            }

            $normalized = self::normalizeChapter($chapter);

            if ($normalized === null) {
                continue;
            }

            $key = $chapter['key'] ?? ('chapter-'.($index + 1));
            $chapters[] = array_merge([
                'key' => is_string($key) ? $key : 'chapter-'.($index + 1),
            ], $normalized);
            $index++;
        }

        return $chapters;
    }

    /**
     * @return array{title: string, actions: array<int, array{label: string, text: string, nested?: bool}>, statuses: array<int, array{key: string, label: string, text: string, pill: string}>, status_note: ?string}|null
     */
    private static function normalizeChapter(array $chapter): ?array
    {
        $title = $chapter['title'] ?? null;

        if (! is_string($title) || $title === '') {
            return null;
        }

        $actions = [];
        $actionItems = $chapter['actions'] ?? [];

        if (is_array($actionItems)) {
            foreach ($actionItems as $action) {
                if (! is_array($action)) {
                    continue;
                }

                $label = $action['label'] ?? null;
                $text = $action['text'] ?? null;

                if (! is_string($label) || $label === '' || ! is_string($text) || $text === '') {
                    continue;
                }

                $entry = [
                    'label' => $label,
                    'text' => $text,
                ];

                if (! empty($action['nested'])) {
                    $entry['nested'] = true;
                }

                $actions[] = $entry;
            }
        }

        $statusItems = $chapter['statuses'] ?? [];
        $statuses = [];

        if (is_array($statusItems)) {
            foreach (TaskStatus::cases() as $status) {
                $key = $status->value;

                if (! isset($statusItems[$key]) || ! is_string($statusItems[$key]) || $statusItems[$key] === '') {
                    continue;
                }

                $statuses[] = [
                    'key' => $key,
                    'label' => __($status->labelKey()),
                    'text' => $statusItems[$key],
                    'pill' => $status->pillModifier(),
                ];
            }
        }

        $statusNote = null;
        $note = $chapter['status_note'] ?? null;

        if (is_string($note) && $note !== '') {
            $statusNote = $note;
        }

        return [
            'title' => $title,
            'actions' => $actions,
            'statuses' => $statuses,
            'status_note' => $statusNote,
        ];
    }
}
