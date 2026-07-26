<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TaskStatus;

/**
 * Per-pagina actie- en statushulp (V1 Actiehulp + Statushulp, unified popup).
 * Inhoud: lang/[locale]/page-help.json → pages.{key}.
 */
final class PageHelp
{
    /**
     * @return array{
     *     title: string,
     *     actions: list<array{label: string, text: string, nested: bool, label_icon: string|null, manual_screenshot_id: string|null}>,
     *     statuses: list<array{key: string, label: string, text: string, pill: string}>,
     *     status_note: string|null,
     * }|null
     */
    public static function for(string $page, array $replace = []): ?array
    {
        /** @var mixed $pages */
        $pages = __('page-help.pages');

        if (! is_array($pages) || ! isset($pages[$page]) || ! is_array($pages[$page])) {
            return null;
        }

        /** @var array<string, mixed> $pageData */
        $pageData = $pages[$page];

        $title = $pageData['title'] ?? null;

        if (! is_string($title) || $title === '') {
            return null;
        }

        $actions = [];

        if (isset($pageData['actions']) && is_array($pageData['actions'])) {
            foreach ($pageData['actions'] as $item) {
                if (! is_array($item) || ! isset($item['label'], $item['text'])) {
                    continue;
                }

                $text = (string) $item['text'];
                foreach ($replace as $key => $value) {
                    $text = str_replace(':'.$key, (string) $value, $text);
                }

                $labelIcon = $item['label_icon'] ?? null;
                $manualScreenshotId = $item['manual_screenshot_id'] ?? null;

                $actions[] = [
                    'label' => (string) $item['label'],
                    'text' => $text,
                    'nested' => ! empty($item['nested']),
                    'label_icon' => is_string($labelIcon) && $labelIcon !== '' ? $labelIcon : null,
                    'manual_screenshot_id' => is_string($manualScreenshotId) && $manualScreenshotId !== '' ? $manualScreenshotId : null,
                ];
            }
        }

        $statusItems = $pageData['statuses'] ?? [];
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

            // Domeinspecifieke statussen (bv. reserveringen) als er geen TaskStatus-keys zijn.
            if ($statuses === []) {
                foreach ($statusItems as $key => $text) {
                    if (! is_string($key) || ! is_string($text) || $text === '') {
                        continue;
                    }

                    $labelKey = 'reservations.lifecycle.'.$key;
                    $label = __($labelKey);
                    if ($label === $labelKey) {
                        $label = $key;
                    }

                    $pill = match ($key) {
                        'pending' => 'new',
                        'confirmed' => 'progress',
                        'cancelled', 'expired' => 'closed',
                        default => 'new',
                    };

                    $statuses[] = [
                        'key' => $key,
                        'label' => $label,
                        'text' => $text,
                        'pill' => $pill,
                    ];
                }
            }
        }

        $statusNote = null;
        $note = $pageData['status_note'] ?? null;

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
