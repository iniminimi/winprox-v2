<?php

namespace App\Livewire\Pages;

use App\Models\InternalTeam;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Team extends Component
{
    public function render()
    {
        return view('livewire.pages.team', [
            'teams' => InternalTeam::query()
                ->with('workers')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
