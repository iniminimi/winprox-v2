<div class="wp-stack" data-manual-capture="settings-api">
    <x-wp-page-head-title
        icon="api"
        :title="__('settings.api.title')"
        help-page="settings.api"
        :subtitle="__('settings.api.subtitle')"
    />

    @if (! $hasApiAccess)
        <div class="wp-card wp-card-pad" style="text-align: center; padding: 2rem;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🔌</div>
            <h2 style="margin-bottom: 0.5rem;">{{ __('settings.api.upgrade_title') }}</h2>
            <p class="wp-text-body" style="margin-bottom: 1.5rem;">{{ __('settings.api.upgrade_message') }}</p>
            <a href="{{ route('subscription.index') }}" class="btn btn--primary">
                {{ __('settings.api.upgrade_button') }}
            </a>
        </div>
    @else
        <div class="wp-card wp-card-pad">
            <a href="{{ route('settings.api.docs') }}" class="btn btn--primary">
                {{ __('settings.api.view_docs') }}
            </a>
        </div>

        @if (session('api_token_plain'))
            <div class="wp-card wp-card-pad">
                <p class="wp-text-body">{{ __('settings.api.token_created') }}</p>
                <code class="wp-code-block">{{ session('api_token_plain') }}</code>
            </div>
        @endif

        @if (session('webhook_tested'))
            <div class="wp-card wp-card-pad">
                <p class="wp-text-body">{{ __('settings.api.webhook_tested') }}</p>
                <code class="wp-code-block">{{ session('webhook_tested') }}</code>
            </div>
        @endif

        <div class="wp-card wp-card-pad wp-stack-tight">
            <h2 class="wp-section-title">{{ __('settings.api.tokens_heading') }}</h2>
            <form wire:submit="createToken" class="wp-stack-tight">
                <input type="text" wire:model="newTokenName" class="wp-input" placeholder="{{ __('settings.api.token_name') }}">
                <div class="wp-chip-row">
                    @foreach ($availableAbilities as $ability => $label)
                        <label class="wp-chip">
                            <input type="checkbox" wire:model="tokenAbilities" value="{{ $ability }}">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="btn btn--primary">{{ __('settings.api.token_create') }}</button>
            </form>
            @forelse ($tokens as $token)
                <div class="wp-data-row" wire:key="token-{{ $token->id }}">
                    <div>
                        <p class="wp-text-body">{{ $token->name }}</p>
                        <p class="wp-muted">{{ implode(', ', $token->abilities ?? ['*']) }}</p>
                    </div>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="revokeToken({{ $token->id }})">
                        {{ __('settings.api.token_revoke') }}
                    </button>
                </div>
            @empty
                <p class="wp-muted">{{ __('settings.api.tokens_empty') }}</p>
            @endforelse
        </div>

        <div class="wp-card wp-card-pad wp-stack-tight">
            <h2 class="wp-section-title">{{ __('settings.api.webhooks_heading') }}</h2>
            <form wire:submit="saveEndpoint" class="wp-stack-tight">
                <input type="url" wire:model="endpointUrl" class="wp-input" placeholder="{{ __('settings.api.endpoint_url') }}">
                <div class="wp-chip-row">
                    @foreach ($availableEvents as $event)
                        <label class="wp-chip">
                            <input type="checkbox" wire:model="endpointEvents" value="{{ $event }}">
                            <span>{{ $event }}</span>
                        </label>
                    @endforeach
                </div>
                <input type="text" wire:model="endpointDescription" class="wp-input" placeholder="{{ __('settings.api.endpoint_description') }}">
                <button type="submit" class="btn btn--primary">{{ __('settings.api.endpoint_save') }}</button>
            </form>

            @forelse ($endpoints as $endpoint)
                <div class="wp-data-row" wire:key="endpoint-{{ $endpoint->id }}">
                    <div>
                        <p class="wp-text-body">{{ $endpoint->url }}</p>
                        <p class="wp-muted">{{ implode(', ', $endpoint->events ?? []) }}</p>
                    </div>
                    <div class="wp-cluster">
                        <span class="wp-pill {{ $endpoint->is_active ? 'wp-pill--done' : 'wp-pill--closed' }}">
                            {{ $endpoint->is_active ? __('settings.api.active') : __('settings.api.inactive') }}
                        </span>
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="testWebhook({{ $endpoint->id }})">
                            {{ __('settings.api.test') }}
                        </button>
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleEndpoint({{ $endpoint->id }})">
                            {{ __('settings.api.toggle') }}
                        </button>
                        <button type="button" class="btn btn--danger btn--sm" wire:click="deleteEndpoint({{ $endpoint->id }})">
                            {{ __('common.button.delete') }}
                        </button>
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('settings.api.endpoints_empty') }}</p>
            @endforelse
        </div>

        <div class="wp-card wp-card-pad wp-stack-tight">
            <h2 class="wp-section-title">{{ __('settings.api.deliveries_heading') }}</h2>
            @forelse ($deliveries as $delivery)
                <div class="wp-data-row" wire:key="delivery-{{ $delivery->id }}">
                    <div>
                        <p class="wp-text-body">{{ $delivery->event }}</p>
                        <p class="wp-muted">{{ $delivery->endpoint?->url }}</p>
                    </div>
                    <div class="wp-cluster">
                        <span class="wp-pill wp-pill--{{ $delivery->status === 'success' ? 'done' : ($delivery->status === 'failed' ? 'closed' : 'progress') }}">
                            {{ $delivery->status }}
                        </span>
                        @if($delivery->status === 'failed')
                            <span class="wp-muted">{{ $delivery->error }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('settings.api.deliveries_empty') }}</p>
            @endforelse
        </div>
    @endif
</div>
