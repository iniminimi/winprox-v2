<div class="wp-stack" style="--wp-stack-gap: 2rem;">
    <div class="wp-row wp-cluster" style="display: flex; flex-direction: row; justify-content: space-between; align-items: center; width: 100%;">
        <div class="wp-stack" style="--wp-stack-gap: 0.25rem;">
            <h1 class="wp-section-title" style="margin: 0;">{{ __('contact-messages.title') }}</h1>
            <p class="wp-text-sm wp-muted" style="margin: 0;">{{ __('contact-messages.subtitle') }}</p>
        </div>

        <div class="wp-row wp-cluster" style="display: flex; flex-direction: row; gap: 0.5rem; align-items: center;">
            <button wire:click="setFilter('all')" 
                class="btn {{ $filter === 'all' ? 'btn--primary' : 'btn--ghost' }} btn--sm">
                {{ __('contact-messages.filter_all') }}
            </button>
            <button wire:click="setFilter('inbound')" 
                class="btn {{ $filter === 'inbound' ? 'btn--primary' : 'btn--ghost' }} btn--sm" style="display: flex; align-items: center; gap: 0.5rem;">
                {{ __('contact-messages.filter_inbound') }}
                @if($unreadCount > 0)
                    <span class="wp-pill wp-pill--new" style="margin: 0;">{{ $unreadCount }}</span>
                @endif
            </button>
            <button wire:click="setFilter('outbound')" 
                class="btn {{ $filter === 'outbound' ? 'btn--primary' : 'btn--ghost' }} btn--sm">
                {{ __('contact-messages.filter_outbound') }}
            </button>
        </div>
    </div>

    <div class="wp-row" style="display: flex; flex-direction: row; gap: 2rem; align-items: start; width: 100%;">
        
        <div class="wp-stack" style="flex: 0 0 40%; width: 40%; --wp-stack-gap: 0.5rem; max-h: calc(100vh - 16rem); overflow-y: auto; padding-right: 0.5rem;">
            @forelse($messages as $message)
                <div wire:click="selectMessage({{ $message->id }})" 
                    class="wp-card wp-list-row {{ $selectedMessage && $selectedMessage->id === $message->id ? 'wp-list-row--active' : '' }}"
                    style="cursor: pointer; display: block; padding: 0.75rem; border-radius: var(--wp-radius, 8px); transition: all 0.2s ease; {{ $selectedMessage && $selectedMessage->id === $message->id ? 'border-color: var(--wp-accent); background-color: var(--wp-accent-soft);' : '' }} {{ $message->direction === 'inbound' && !$message->read_at ? 'border-left: 3px solid var(--wp-accent);' : '' }}">
                    
                    <div class="wp-stack" style="--wp-stack-gap: 0.125rem;">
                        <div class="wp-row" style="display: flex; flex-direction: row; justify-content: space-between; align-items: center; width: 100%;">
                            <div class="wp-row wp-cluster" style="display: flex; flex-direction: row; gap: 0.5rem; align-items: center;">
                                @if($message->direction === 'inbound' && !$message->read_at)
                                    <span class="wp-pill wp-pill--new" style="font-size: 0.7rem; padding: 0.125rem 0.375rem;">{{ __('contact-messages.badge_new') }}</span>
                                @elseif($message->direction === 'outbound')
                                    <span class="wp-pill wp-pill--closed" style="font-size: 0.7rem; padding: 0.125rem 0.375rem;">{{ __('contact-messages.badge_sent') }}</span>
                                @endif
                            </div>
                        </div>
                        
                        <div class="wp-text-body" style="font-weight: 600; font-size: 0.9rem; line-height: 1.3;">
                            {{ Str::limit($message->subject, 50) }}
                        </div>
                        
                        <div class="wp-text-sm wp-muted truncate" style="font-size: 0.8rem;">
                            {{ $message->name ?? $message->user?->name }}
                        </div>
                    </div>

                </div>
            @empty
                <div class="wp-card wp-card-pad" style="text-align: center; padding: 2rem;">
                    <p class="wp-text-body wp-muted">{{ __('contact-messages.no_messages') }}</p>
                </div>
            @endforelse

            <div style="padding-top: 0.5rem;">
                {{ $messages->links() }}
            </div>
        </div>

        <div style="flex: 0 0 60%; width: 60%;">
            @if($selectedMessage)
                <div class="wp-card wp-card-pad wp-stack" style="--wp-stack-gap: 1.5rem; border-radius: var(--wp-radius, 16px);">
                    
                    <div class="wp-stack" style="--wp-stack-gap: 0.75rem; border-bottom: 1px solid rgba(0,0,0,0.06); padding-bottom: 1.25rem; width: 100%;">
                        <div class="wp-row" style="display: flex; flex-direction: row; justify-content: space-between; align-items: start; width: 100%;">
                            <h2 class="wp-section-title" style="margin: 0; font-size: 1.35rem;">{{ $selectedMessage->subject }}</h2>
                            <span class="wp-text-sm wp-muted font-medium bg-slate-50 dark:bg-slate-800/40 px-2 py-1 rounded">{{ $selectedMessage->created_at->format('d-m-Y H:i') }}</span>
                        </div>
                        
                        <div class="wp-text-sm wp-muted" style="line-height: 1.5;">
                            <span style="display: block;"><strong>{{ __('contact-messages.label_from') }}:</strong> {{ $selectedMessage->name }} &lt;{{ $selectedMessage->email }}&gt;</span>
                            @if($selectedMessage->phone)
                                <span style="display: block; margin-top: 0.25rem;"><strong>{{ __('contact-messages.label_phone') }}:</strong> {{ $selectedMessage->phone }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="wp-text-body" style="white-space: pre-wrap; line-height: 1.6; min-height: 10rem; font-size: 0.95rem;">
                        {{ $selectedMessage->message }}
                    </div>

                    @if($selectedMessage->direction === 'inbound')
                        <div class="wp-stack" style="--wp-stack-gap: 1rem; border-top: 1px solid rgba(0,0,0,0.06); padding-top: 1.25rem; width: 100%;">
                            <label class="wp-label" style="font-weight: 600; font-size: 0.85rem; text-transform: uppercase; tracking-wider: 0.05em;">
                                {{ __('contact-messages.title_reply') }}
                            </label>
                            
                            <textarea wire:model="reply" rows="5" class="wp-input" 
                                placeholder="{{ __('contact-messages.placeholder_reply') }}"
                                style="width: 100%; resize: vertical; border-radius: 8px; padding: 0.75rem;"></textarea>
                            
                            @error('reply')
                                <span class="wp-error" style="display: block; font-size: 0.85rem;">{{ $message }}</span>
                            @enderror

                            <div class="wp-row" style="display: flex; flex-direction: row; justify-content: flex-end; width: 100%;">
                                <button wire:click="sendReply" class="btn primary">
                                    {{ __('contact-messages.button_send') }}
                                </button>
                            </div>
                        </div>
                    @endif

                </div>
            @else
                <div class="wp-card wp-card-pad" style="text-align: center; padding: 6rem 2rem; display: flex; flex-direction: column; justify-content: center; align-items: center; border-radius: var(--wp-radius, 16px);">
                    <div class="wp-stack" style="--wp-stack-gap: 0.75rem; align-items: center; max-width: 360px; margin: 0 auto;">
                        <h3 class="wp-section-title" style="margin: 0; font-size: 1.2rem;">{{ __('contact-messages.empty_title') }}</h3>
                        <p class="wp-text-body wp-muted" style="margin: 0; font-size: 0.9rem; line-height: 1.5;">
                            {{ __('contact-messages.empty_body') }}
                        </p>
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>