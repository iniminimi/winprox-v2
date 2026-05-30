<?php

namespace App\Livewire\Pages;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('WinProx')]
class Contact extends Component
{
    public function render()
    {
        $layout = auth()->check()
            ? 'components.layouts.app'
            : 'components.layouts.public';

        return view('livewire.pages.contact')
            ->layout($layout);
    }
}
