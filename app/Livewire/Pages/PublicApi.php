<?php

namespace App\Livewire\Pages;

use App\Support\Marketing\JsonLd;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('WinProx')]
class PublicApi extends Component
{
    public function render()
    {
        return view('livewire.pages.api-public', [
            'relatedLinks' => [
                ['label' => __('api_public.links.about'), 'url' => route('about')],
                ['label' => __('api_public.links.pricing'), 'url' => route('pricing')],
                ['label' => __('api_public.links.facility'), 'url' => route('features.facility')],
                ['label' => __('api_public.links.faq'), 'url' => route('faq.public')],
                ['label' => __('api_public.links.contact'), 'url' => route('contact.index')],
            ],
        ])->layout('components.layouts.marketing', [
            'title' => __('api_public.meta_title'),
            'socialTitle' => __('api_public.social.og_title'),
            'socialDescription' => __('api_public.social.og_description'),
            'jsonLdGraphs' => [
                JsonLd::organization(),
                JsonLd::softwareApplication(),
            ],
        ]);
    }
}
