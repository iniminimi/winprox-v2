<?php

namespace App\Livewire\Pages;

use App\Support\Marketing\JsonLd;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('WinProx')]
class About extends Component
{
    public function render()
    {
        return view('livewire.pages.about', [
            'relatedLinks' => $this->relatedLinks(),
        ])->layout('components.layouts.marketing', [
            'title' => __('about.meta_title'),
            'socialTitle' => __('about.social.og_title'),
            'socialDescription' => __('about.social.og_description'),
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
        return [
            ['label' => __('about.links.facility'), 'url' => route('features.facility')],
            ['label' => __('about.links.time'), 'url' => route('features.time')],
            ['label' => __('about.links.esg'), 'url' => route('features.esg')],
            ['label' => __('about.links.qr'), 'url' => route('features.qr')],
            ['label' => __('about.links.api'), 'url' => route('api.public')],
            ['label' => __('about.links.faq'), 'url' => route('faq.public')],
            ['label' => __('about.links.pricing'), 'url' => route('pricing')],
        ];
    }
}
