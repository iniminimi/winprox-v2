<?php

namespace App\Livewire\Pages;

use App\Support\Marketing\JsonLd;
use App\Support\Marketing\MarketingDomain;
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
        $links = [
            ['label' => __('landings.prikklok.nav_label'), 'url' => route('prikklok')],
        ];

        if (! MarketingDomain::routeHidden('checkmate')) {
            $links[] = ['label' => __('about.links.checkmate'), 'url' => route('checkmate')];
        }

        $links[] = ['label' => __('about.links.time'), 'url' => route('features.time')];
        $links[] = ['label' => __('about.links.api'), 'url' => route('product.api_webhooks')];
        $links[] = ['label' => __('about.links.pricing'), 'url' => route('pricing')];

        return $links;
    }
}
