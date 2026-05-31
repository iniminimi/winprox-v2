<?php

namespace App\Support\Faq;

class FaqSections
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function orderedItems(): array
    {
        $order = config('faq.section_order', []);
        $raw = __('faq.items');

        if (! is_array($order) || ! is_array($raw)) {
            return [];
        }

        $items = [];

        foreach ($order as $slug) {
            if (! is_string($slug)) {
                continue;
            }

            $item = $raw[$slug] ?? null;

            if (! is_array($item) || ! isset($item['slug'], $item['title'], $item['type'])) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }
}
