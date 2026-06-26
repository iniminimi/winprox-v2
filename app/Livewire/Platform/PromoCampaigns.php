<?php

namespace App\Livewire\Platform;

use App\Actions\Marketing\CreatePromoCampaignAction;
use App\Http\Requests\Marketing\CreatePromoCampaignRequest;
use App\Models\PromoCampaign;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class PromoCampaigns extends Component
{
    use AuthorizesRequests;

    public string $slug = '';

    public string $name = '';

    public string $locale = 'nl';

    public ?string $flashMessage = null;

    public string $flashType = 'success';

    public function mount(): void
    {
        $this->authorize('managePromoCampaigns', User::class);
    }

    public function createCampaign(CreatePromoCampaignAction $create): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        $this->slug = strtolower(trim($this->slug));
        $this->locale = strtolower(trim($this->locale));

        $validated = $this->validate(
            CreatePromoCampaignRequest::ruleSet(),
            CreatePromoCampaignRequest::validationMessages(),
        );
        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $campaign = $create->handle(
            slug: $validated['slug'],
            name: $validated['name'],
            locale: $validated['locale'],
            actorUserId: (int) $user->id,
        );

        $this->reset(['slug', 'name']);
        $this->locale = 'nl';

        $this->redirect(route('platform.promo-campaigns.edit', $campaign), navigate: true);
    }

    public function render()
    {
        return view('livewire.platform.promo-campaigns', [
            'campaigns' => PromoCampaign::query()->latest('id')->get(),
        ]);
    }
}
