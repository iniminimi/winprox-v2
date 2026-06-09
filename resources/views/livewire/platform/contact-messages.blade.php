<div class="wp-stack" style="--wp-stack-gap: 1.25rem;">
    <div class="wp-row wp-cluster" style="display: flex; flex-direction: row; justify-content: space-between; align-items: center; width: 100%;">
        <div class="wp-stack" style="--wp-stack-gap: 0.125rem;">
            <h1 class="wp-section-title" style="margin: 0; font-size: 1.4rem;">{{ __('contact-messages.title') }}</h1>
            <p class="wp-text-sm wp-muted" style="margin: 0;">{{ __('contact-messages.subtitle') }}</p>
        </div>

        <div class="wp-row wp-cluster" style="display: flex; flex-direction: row; gap: 0.375rem; align-items: center;">
            <button wire:click="setFilter('inbound')"
                class="btn {{ $filter === 'inbound' ? 'btn--primary' : 'btn--ghost' }} btn--sm" style="display: flex; align-items: center; gap: 0.375rem;">
                {{ __('contact-messages.filter_inbound') }}
                @if($unreadCount > 0)
                    <span class="wp-pill wp-pill--new" style="margin: 0; padding: 0.05rem 0.3rem; font-size: 0.75rem;">{{ $unreadCount }}</span>
                @endif
            </button>
            <button wire:click="setFilter('outbound')"
                class="btn {{ $filter === 'outbound' ? 'btn--primary' : 'btn--ghost' }} btn--sm">
                {{ __('contact-messages.filter_outbound') }}
            </button>
        </div>
    </div>

    <div class="wp-row" style="display: flex; flex-direction: row; gap: 1.25rem; align-items: start; width: 100%;">
        
        <div class="wp-stack" style="flex: 0 0 38%; width: 38%; --wp-stack-gap: 0.25rem; max-h: calc(100vh - 12rem); overflow-y: auto; padding-right: 0.25rem; margin-top: 0;">
            @forelse($messages as $message)
                <div wire:click="selectMessage({{ $message->id }})" 
                    class="wp-card wp-list-row {{ $selectedMessage && $selectedMessage->id === $message->id ? 'wp-list-row--active' : '' }}"
                    style="cursor: pointer; display: block; padding: 0.4rem 0.6rem; border-radius: var(--wp-radius, 6px); transition: all 0.15s ease; border: 1px solid rgba(0,0,0,0.04); {{ $selectedMessage && $selectedMessage->id === $message->id ? 'border-color: var(--wp-accent); background-color: var(--wp-accent-soft);' : '' }} {{ $message->direction === 'inbound' && !$message->read_at ? 'border-left: 3px solid var(--wp-accent); font-weight: 600;' : '' }}">
                    
                    <div class="wp-stack" style="--wp-stack-gap: 0.15rem;">
                        <div class="wp-row" style="display: flex; flex-direction: row; justify-content: space-between; align-items: center; width: 100%;">
                            <div style="display: flex; align-items: center;">
                                @if($message->direction === 'inbound')
                                    <span class="wp-pill {{ !$message->read_at ? 'wp-pill--new' : 'wp-pill--progress' }}" style="font-size: 0.65rem; padding: 0.05rem 0.25rem;">In</span>
                                @else
                                    <span class="wp-pill wp-pill--closed" style="font-size: 0.65rem; padding: 0.05rem 0.25rem;">Out</span>
                                @endif
                            </div>
                            <span class="wp-text-sm wp-muted" style="font-size: 0.75rem;">{{ $message->created_at->format('d-m H:i') }}</span>
                        </div>
                        
                        <div class="wp-text-body" style="font-weight: 600; font-size: 0.85rem; line-height: 1.25; color: #1e293b; margin: 0;">
                            {{ Str::limit($message->subject, 45) }}
                        </div>
                        
                        <div class="wp-text-sm wp-muted truncate" style="font-size: 0.75rem; color: #64748b; margin: 0;">
                            {{ $message->name ?? $message->user?->name }}
                        </div>
                    </div>

                </div>
            @empty
                <div class="wp-card wp-card-pad" style="text-align: center; padding: 1.5rem;">
                    <p class="wp-text-body wp-muted" style="font-size: 0.85rem;">{{ __('contact-messages.no_messages') }}</p>
                </div>
            @endforelse

            @if($messages->hasPages())
                <div style="padding-top: 0.25rem; font-size: 0.8rem;">
                    {{ $messages->links() }}
                </div>
            @endif
        </div>

        <div style="flex: 0 0 62%; width: 62%;">
            @if($selectedMessage)
                <div class="wp-card wp-card-pad wp-stack" style="--wp-stack-gap: 1rem; border-radius: var(--wp-radius, 12px); padding: 1.25rem;">
                    
                    <div class="wp-stack" style="--wp-stack-gap: 0.375rem; border-bottom: 1px solid rgba(0,0,0,0.06); padding-bottom: 0.75rem; width: 100%;">
                        <div class="wp-row" style="display: flex; flex-direction: row; justify-content: space-between; align-items: start; width: 100%;">
                            <h2 class="wp-section-title" style="margin: 0; font-size: 1.2rem;">{{ $selectedMessage->subject }}</h2>
                            <span class="wp-text-sm wp-muted" style="font-size: 0.75rem; background: rgba(0,0,0,0.03); padding: 0.15rem 0.4rem; border-radius: 4px;">{{ $selectedMessage->created_at->format('d-m-Y H:i') }}</span>
                        </div>
                        
                        <div class="wp-text-sm wp-muted" style="line-height: 1.4; font-size: 0.85rem;">
                            <span><strong>{{ __('contact-messages.label_from') }}:</strong> {{ $selectedMessage->name }} &lt;{{ $selectedMessage->email }}&gt;</span>
                            @if($selectedMessage->phone)
                                <span style="display: block; margin-top: 0.15rem;"><strong>{{ __('contact-messages.label_phone') }}:</strong> {{ $selectedMessage->phone }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="wp-text-body" style="white-space: pre-wrap; line-height: 1.5; min-height: 12rem; font-size: 0.9rem; color: #334155; word-break: break-word; overflow-wrap: anywhere; width: 100%;">
                        {{ $selectedMessage->message }}
                    </div>

                    @if($selectedMessage->direction === 'inbound')
                        <div class="wp-stack" style="--wp-stack-gap: 0.5rem; border-top: 1px solid rgba(0,0,0,0.06); padding-top: 0.75rem; width: 100%;">
                            <label class="wp-label" style="font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin: 0;">
                                {{ __('contact-messages.title_reply') }}
                            </label>
                            
                            <textarea wire:model="reply" rows="4" class="wp-input" 
                                placeholder="{{ __('contact-messages.placeholder_reply') }}"
                                style="width: 100%; resize: vertical; border-radius: 6px; padding: 0.5rem 0.75rem; font-size: 0.9rem;"></textarea>
                            
                            @error('reply')
                                <span class="wp-error" style="display: block; font-size: 0.8rem;">{{ $message }}</span>
                            @enderror

                            <div class="wp-row" style="display: flex; flex-direction: row; justify-content: flex-end; width: 100%;">
                                <button wire:click="sendReply" class="btn primary btn--sm">
                                    {{ __('contact-messages.button_send') }}
                                </button>
                            </div>
                        </div>
                    @endif

                </div>
            @else
                <div class="wp-card wp-card-pad" style="text-align: center; padding: 7rem 2rem; display: flex; flex-direction: column; justify-content: center; align-items: center; border-radius: var(--wp-radius, 12px); min-height: 24rem;">
                    <div class="wp-stack" style="--wp-stack-gap: 0.5rem; align-items: center; max-width: 320px; margin: 0 auto;">
                        <h3 class="wp-section-title" style="margin: 0; font-size: 1.1rem;">{{ __('contact-messages.empty_title') }}</h3>
                        <p class="wp-text-body wp-muted" style="margin: 0; font-size: 0.85rem; line-height: 1.4;">
                            {{ __('contact-messages.empty_body') }}
                        </p>
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>