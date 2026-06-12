<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class ManualHub extends Component
{
    public function render(): \Illuminate\View\View
    {
        return view('livewire.pages.manual-hub');
    }
}
