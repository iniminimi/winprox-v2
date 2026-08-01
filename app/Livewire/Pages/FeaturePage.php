<?php

namespace App\Livewire\Pages;

use App\Support\Marketing\JsonLd;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('WinProx')]
class FeaturePage extends Component
{
    /** @var list<string> */
    public const SLUGS = ['facility', 'time', 'esg', 'iot', 'qr'];

    public string $slug = '';

    public function mount(): void
    {
        $slug = match (true) {
            request()->routeIs('features.facility') => 'facility',
            request()->routeIs('features.time') => 'time',
            request()->routeIs('features.esg') => 'esg',
            request()->routeIs('features.iot') => 'iot',
            request()->routeIs('features.qr') => 'qr',
            default => null,
        };

        abort_unless(is_string($slug) && in_array($slug, self::SLUGS, true), 404);
        $this->slug = $slug;
    }

    public function render()
    {
        $key = 'features.'.$this->slug;

        return view('livewire.pages.feature', [
            'slug' => $this->slug,
            'relatedLinks' => $this->relatedLinks(),
        ])->layout('components.layouts.marketing', [
            'title' => __("{$key}.meta_title"),
            'socialTitle' => __("{$key}.social.og_title"),
            'socialDescription' => __("{$key}.social.og_description"),
            'jsonLdGraphs' => [
                JsonLd::organization(),
                JsonLd::softwareApplication(),
            ],
        ]);
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function relatedLinks(): array
    {
        $links = [];
        foreach (self::SLUGS as $slug) {
            if ($slug === $this->slug) {
                continue;
            }
            $links[] = [
                'label' => __('features.'.$slug.'.nav_label'),
                'url' => route('features.'.$slug),
            ];
        }

        $links[] = ['label' => __('features.shared.links.about'), 'url' => route('about')];
        $links[] = ['label' => __('features.shared.links.api'), 'url' => route('product.api_webhooks')];
        $links[] = ['label' => __('features.shared.links.faq'), 'url' => route('faq.public')];
        $links[] = ['label' => __('features.shared.links.pricing'), 'url' => route('pricing')];

        return $links;
    }
}
