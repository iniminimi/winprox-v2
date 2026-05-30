<?php

namespace App\Livewire\Pages;

use App\Actions\Api\CreateApiTokenAction;
use App\Actions\Api\RevokeApiTokenAction;
use App\Actions\Webhooks\DeleteWebhookEndpointAction;
use App\Actions\Webhooks\SetWebhookEndpointActiveAction;
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
        $this->authorize('viewAny', WebhookEndpoint::class);
    }

    public function saveEndpoint(StoreWebhookEndpointAction $store): void
    {
        $this->authorize('create', WebhookEndpoint::class);

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
        ], (int) Tenancy::id(), (int) auth()->id());

        $this->reset(['endpointUrl', 'endpointEvents', 'endpointDescription']);
    }

    public function toggleEndpoint(int $id, SetWebhookEndpointActiveAction $setActive): void
    {
        $endpoint = WebhookEndpoint::query()->findOrFail($id);
        $this->authorize('update', $endpoint);

        $setActive->handle($endpoint, ! $endpoint->is_active, (int) auth()->id());
    }

    public function deleteEndpoint(int $id, DeleteWebhookEndpointAction $delete): void
    {
        $endpoint = WebhookEndpoint::query()->findOrFail($id);
        $this->authorize('delete', $endpoint);

        $delete->handle($endpoint, (int) auth()->id());
    }

    public function createToken(CreateApiTokenAction $createToken): void
    {
        $this->authorize('manageApiTokens', WebhookEndpoint::class);

        $this->validate(['newTokenName' => ['required', 'string', 'max:80']]);

        $plain = $createToken->handle(auth()->user(), $this->newTokenName, (int) auth()->id());
        session()->flash('api_token_plain', $plain);
        $this->newTokenName = 'api';
    }

    public function revokeToken(int $tokenId, RevokeApiTokenAction $revokeToken): void
    {
        $this->authorize('manageApiTokens', WebhookEndpoint::class);

        $revokeToken->handle(auth()->user(), $tokenId, (int) auth()->id());
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
