<?php

namespace App\Livewire\Pages;

use App\Models\Location;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Locations extends Component
{
    public function render()
    {
        return view('livewire.pages.locations', [
            'locations' => Location::query()
                ->with('units')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
