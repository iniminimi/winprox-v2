<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Actions\Contact\ListEmailUnsubscribesAction;
use App\Actions\Contact\SetEmailSubscriptionAction;
use App\Http\Requests\Contact\StoreEmailUnsubscribeRequest;
use App\Models\EmailUnsubscribe;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class EmailUnsubscribes extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public bool $undeliverableOnly = false;

    #[Url]
    public bool $manualOnly = false;

    public string $newEmail = '';

    public ?string $flashMessage = null;

    public string $flashType = 'success';

    public function mount(): void
    {
        $this->authorize('viewAny', EmailUnsubscribe::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedUndeliverableOnly(): void
    {
        $this->resetPage();
    }

    public function updatedManualOnly(): void
    {
        $this->resetPage();
    }

    public function add(SetEmailSubscriptionAction $setSubscription): void
    {
        $this->authorize('create', EmailUnsubscribe::class);

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $this->validate(StoreEmailUnsubscribeRequest::ruleSet());

        $email = EmailUnsubscribe::normalizeEmail($this->newEmail);
        $setSubscription->handle($email, true, (int) $user->id);

        $this->newEmail = '';
        $this->flashType = 'success';
        $this->flashMessage = __('platform.email_unsubscribe.added', ['email' => $email]);
        $this->resetPage();
    }

    public function restore(int $emailUnsubscribeId, SetEmailSubscriptionAction $setSubscription): void
    {
        $row = EmailUnsubscribe::query()->findOrFail($emailUnsubscribeId);
        $this->authorize('delete', $row);

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $email = $row->email;
        $setSubscription->handle($email, false, (int) $user->id);

        $this->flashType = 'success';
        $this->flashMessage = __('platform.email_unsubscribe.restored', ['email' => $email]);
    }

    public function render(ListEmailUnsubscribesAction $list)
    {
        $result = $list->handle(
            search: $this->search,
            page: $this->getPage(),
            undeliverableOnly: $this->undeliverableOnly,
            manualOnly: $this->manualOnly,
        );

        return view('livewire.platform.email-unsubscribes', [
            'rows' => $result['rows'],
            'matchedUsers' => $result['matchedUsers'],
            'undeliverableCount' => $result['undeliverableCount'],
            'manualCount' => $result['manualCount'],
        ]);
    }
}
