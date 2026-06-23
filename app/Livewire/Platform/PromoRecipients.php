<?php

namespace App\Livewire\Platform;

use App\Actions\Marketing\CreatePromoRecipientAction;
use App\Http\Requests\Marketing\CreatePromoRecipientRequest;
use App\Models\PromoRecipient;
use App\Models\PromoVisit;
use App\Models\User;
use App\Support\Marketing\PromoLandingUrl;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class PromoRecipients extends Component
{
    use AuthorizesRequests;

    public string $label = '';

    public string $note = '';

    public ?int $expandedRecipientId = null;

    public function mount(): void
    {
        $this->authorize('managePromoRecipients', User::class);
    }

    public function createRecipient(CreatePromoRecipientAction $create): void
    {
        $this->authorize('managePromoRecipients', User::class);

        $validated = $this->validate(CreatePromoRecipientRequest::ruleSet());

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $recipient = $create->handle(
            label: $validated['label'],
            note: $validated['note'] ?? null,
            actorUserId: (int) $user->id,
        );

        $this->reset(['label', 'note']);
        $this->expandedRecipientId = (int) $recipient->id;

        $this->dispatch(
            'promo-recipient-qr-download',
            url: route('platform.promo-recipients.qr', $recipient),
        );
    }

    public function toggleRecipient(int $recipientId): void
    {
        $this->expandedRecipientId = $this->expandedRecipientId === $recipientId ? null : $recipientId;
    }

    public function render()
    {
        $recipients = PromoRecipient::query()
            ->withCount(['visits', 'videoPlays'])
            ->with(['videoPlays'])
            ->latest('id')
            ->get();

        $anonymousVisits = PromoVisit::query()
            ->whereNull('promo_recipient_id')
            ->latest('visited_at')
            ->limit(25)
            ->get();

        $anonymousVisitCount = PromoVisit::query()
            ->whereNull('promo_recipient_id')
            ->count();

        $expandedVisits = collect();
        if ($this->expandedRecipientId !== null) {
            $expandedVisits = PromoVisit::query()
                ->where('promo_recipient_id', $this->expandedRecipientId)
                ->latest('visited_at')
                ->limit(50)
                ->get();
        }

        return view('livewire.platform.promo-recipients', [
            'recipients' => $recipients,
            'anonymousVisitCount' => $anonymousVisitCount,
            'anonymousVisits' => $anonymousVisits,
            'expandedVisits' => $expandedVisits,
            'anonymousPromoUrl' => PromoLandingUrl::anonymous(),
        ]);
    }
}
