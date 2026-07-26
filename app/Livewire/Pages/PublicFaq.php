<?php

namespace App\Livewire\Pages;

use App\Support\Faq\FaqSections;
use App\Support\Marketing\JsonLd;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('WinProx')]
class PublicFaq extends Component
{
    public function render()
    {
        return view('livewire.pages.faq-public', [
            'items' => FaqSections::orderedItems(),
        ])->layout('components.layouts.marketing', [
            'title' => __('faq.meta_title'),
            'socialTitle' => __('faq.social.og_title'),
            'socialDescription' => __('faq.social.og_description'),
            'jsonLdGraphs' => [
                JsonLd::organization(),
                JsonLd::faqPage(),
            ],
        ]);
    }
}
