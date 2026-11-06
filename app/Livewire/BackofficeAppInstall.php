<?php

namespace App\Livewire;

use Livewire\Component;

class BackofficeAppInstall extends Component
{
    public bool $helpOpen = false;

    public function openHelp(): void
    {
        $this->helpOpen = true;
    }

    public function closeHelp(): void
    {
        $this->helpOpen = false;
    }

    public function render()
    {
        return view('livewire.backoffice-app-install');
    }
}
