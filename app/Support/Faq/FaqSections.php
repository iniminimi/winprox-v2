<?php

namespace App\Support\Faq;

use App\Support\Marketing\MarketingDomain;

class FaqSections
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function orderedItems(): array
    {
        $order = config('faq.section_order', []);
        $raw = __('faq.items');
        $hidden = MarketingDomain::hiddenFaqItems();

        if (! is_array($order) || ! is_array($raw)) {
            return [];
        }

        $items = [];

        foreach ($order as $slug) {
            if (! is_string($slug) || in_array($slug, $hidden, true)) {
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
