<?php

namespace App\Livewire\Pages;

use App\Support\Faq\FaqSections;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('WinProx')]
class PublicFaq extends Component
{
    public function render()
    {
        $layout = auth()->check()
            ? 'components.layouts.app'
            : 'components.layouts.marketing';

        return view('livewire.pages.faq-public', [
            'items' => FaqSections::orderedItems(),
        ])->layout($layout, [
            'title' => __('faq.meta_title'),
            'socialTitle' => __('faq.social.og_title'),
            'socialDescription' => __('faq.social.og_description'),
        ]);
    }
}
