<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Actions\ReleaseNotes\DismissWhatsNewCardAction;
use App\Actions\ReleaseNotes\ListPublishedReleaseNotesAction;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class WhatsNew extends Component
{
    use AuthorizesRequests;

    public function mount(DismissWhatsNewCardAction $dismiss): void
    {
        $this->authorize('viewWhatsNew', User::class);

        $user = auth()->user();
        if ($user instanceof User) {
            $dismiss->handle($user);
        }
    }

    public function render(ListPublishedReleaseNotesAction $notes)
    {
        return view('livewire.pages.whats-new', [
            'notes' => $notes->recent(),
        ]);
    }
}
