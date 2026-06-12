<?php

declare(strict_types=1);

namespace App\Support\Manual;

use App\Enums\TaskStatus;

final class ManualPortalStatuses
{
    /**
     * @return array{
     *     title: string,
     *     intro: string,
     *     statuses: list<array{key: string, label: string, text: string, pill: string}>,
     * }|null
     */
    public static function block(string $section): ?array
    {
        /** @var mixed $data */
        $data = __('manual.portal_statuses.'.$section);

        if (! is_array($data)) {
            return null;
        }

        $title = $data['title'] ?? null;
        $intro = $data['intro'] ?? null;

        if (! is_string($title) || $title === '' || ! is_string($intro) || $intro === '') {
            return null;
        }

        $statuses = [];

        foreach (TaskStatus::cases() as $status) {
            $text = $data[$status->value] ?? null;

            if (! is_string($text) || $text === '') {
                continue;
            }

            $statuses[] = [
                'key' => $status->value,
                'label' => __($status->labelKey()),
                'text' => $text,
                'pill' => $status->pillModifier(),
            ];
        }

        if ($statuses === []) {
            return null;
        }

        return [
            'title' => $title,
            'intro' => $intro,
            'statuses' => $statuses,
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     intro: string,
     *     actions: list<empty>,
     *     statuses: list<array{key: string, label: string, text: string, pill: string}>,
     *     status_note: null,
     * }|null
     */
    public static function asChapter(string $section): ?array
    {
        $block = self::block($section);

        if ($block === null) {
            return null;
        }

        return [
            'key' => 'statuses.'.str_replace('_', '-', $section),
            'title' => $block['title'],
            'intro' => $block['intro'],
            'actions' => [],
            'statuses' => $block['statuses'],
            'status_note' => null,
        ];
    }
}
