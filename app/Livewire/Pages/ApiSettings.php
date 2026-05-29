<?php

namespace App\Livewire\Pages;

use App\Actions\Webhooks\StoreWebhookEndpointAction;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class ApiSettings extends Component
{
    use AuthorizesRequests;

    public string $endpointUrl = '';

    /** @var list<string> */
    public array $endpointEvents = [];

    public string $endpointDescription = '';

    public string $newTokenName = 'api';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function saveEndpoint(StoreWebhookEndpointAction $store): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $validated = $this->validate([
            'endpointUrl' => ['required', 'url', 'max:500'],
            'endpointEvents' => ['required', 'array', 'min:1'],
            'endpointEvents.*' => ['string'],
            'endpointDescription' => ['nullable', 'string', 'max:255'],
        ]);

        $store->handle([
            'url' => $validated['endpointUrl'],
            'events' => $validated['endpointEvents'],
            'description' => $validated['endpointDescription'] ?: null,
        ], (int) Tenancy::id());

        $this->reset(['endpointUrl', 'endpointEvents', 'endpointDescription']);
    }

    public function toggleEndpoint(int $id): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $endpoint = WebhookEndpoint::query()->findOrFail($id);
        $endpoint->update(['is_active' => ! $endpoint->is_active]);
    }

    public function deleteEndpoint(int $id): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        WebhookEndpoint::query()->whereKey($id)->delete();
    }

    public function createToken(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->validate(['newTokenName' => ['required', 'string', 'max:80']]);

        $plain = auth()->user()->createToken($this->newTokenName)->plainTextToken;
        session()->flash('api_token_plain', $plain);
        $this->newTokenName = 'api';
    }

    public function revokeToken(int $tokenId): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        auth()->user()->tokens()->whereKey($tokenId)->delete();
    }

    public function render()
    {
        return view('livewire.pages.api-settings', [
            'endpoints' => WebhookEndpoint::query()->orderByDesc('id')->get(),
            'deliveries' => WebhookDelivery::query()
                ->with('endpoint')
                ->orderByDesc('id')
                ->limit(20)
                ->get(),
            'tokens' => auth()->user()->tokens()->orderByDesc('id')->get(),
            'availableEvents' => WebhookEndpoint::AVAILABLE_EVENTS,
        ]);
    }
}
