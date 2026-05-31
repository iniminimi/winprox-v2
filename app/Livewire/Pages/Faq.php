<?php

namespace App\Livewire\Pages;

use App\Support\Faq\FaqSections;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Faq extends Component
{
    public ?string $openSlug = null;

    public function toggle(string $slug): void
    {
        $this->openSlug = $this->openSlug === $slug ? null : $slug;
    }

    public function render()
    {
        return view('livewire.pages.faq', [
            'items' => FaqSections::orderedItems(),
        ]);
    }
}
