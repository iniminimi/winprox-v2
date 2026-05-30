<?php

namespace App\Livewire\Pages;

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
        $raw = __('faq.items');
        $items = is_array($raw) ? array_values(array_filter(
            $raw,
            fn ($item) => is_array($item) && isset($item['slug'], $item['title'], $item['body'])
        )) : [];

        return view('livewire.pages.faq', [
            'items' => $items,
        ]);
    }
}
