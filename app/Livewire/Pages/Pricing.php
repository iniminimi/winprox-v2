<?php

namespace App\Livewire\Pages;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('WinProx')]
class Pricing extends Component
{
    public function render()
    {
        $layout = auth()->check()
            ? 'components.layouts.app'
            : 'components.layouts.marketing';

        return view('livewire.pages.pricing-unavailable')
            ->layout($layout, [
                'title' => __('pricing.meta_title'),
                'socialTitle' => __('pricing.social.og_title'),
                'socialDescription' => __('pricing.social.og_description'),
            ]);
    }
}
