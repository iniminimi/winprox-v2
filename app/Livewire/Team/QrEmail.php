<?php

namespace App\Livewire\Team;

use App\Actions\Team\SendTeamQrEmailAction;
use App\Http\Requests\Team\SendTeamQrEmailRequest;
use App\Models\InternalTeam;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

class QrEmail extends Component
{
    use AuthorizesRequests;

    public InternalTeam $team;

    public string $portalUrl;

    public bool $showModal = false;

    public string $recipientEmail = '';

    public string $recipientName = '';

    public function mount(InternalTeam $team, string $portalUrl): void
    {
        $this->team = $team;
        $this->portalUrl = $portalUrl;

        $this->authorize('update', $team);
    }

    public function openModal(): void
    {
        $this->authorize('update', $this->team);
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetErrorBag();
    }

    public function send(SendTeamQrEmailAction $sendTeamQrEmail): void
    {
        $this->authorize('update', $this->team);

        $validated = Validator::make(
            [
                'recipientEmail' => $this->recipientEmail,
                'recipientName' => $this->recipientName,
            ],
            SendTeamQrEmailRequest::rules(),
            SendTeamQrEmailRequest::messages(),
        )->validate();

        $sendTeamQrEmail->handle(
            $this->team,
            $this->portalUrl,
            $validated['recipientEmail'],
            (int) auth()->id(),
            trim((string) ($validated['recipientName'] ?? '')),
            auth()->user()->locale ?? app()->getLocale(),
        );

        $this->reset(['recipientEmail', 'recipientName']);
        $this->showModal = false;

        session()->flash('team_qr_email_sent', $validated['recipientEmail']);
    }

    public function render()
    {
        return view('livewire.team.qr-email');
    }
}
