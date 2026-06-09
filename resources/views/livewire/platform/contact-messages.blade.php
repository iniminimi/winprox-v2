<div class="wp-stack" style="--wp-stack-gap: 1.25rem;">
    <div class="wp-row wp-cluster" style="display: flex; flex-direction: row; justify-content: space-between; align-items: center; width: 100%;">
        <div class="wp-stack" style="--wp-stack-gap: 0.125rem;">
            <h1 class="wp-section-title" style="margin: 0; font-size: 1.4rem;">{{ __('contact-messages.title') }}</h1>
            <p class="wp-text-sm wp-muted" style="margin: 0;">{{ __('contact-messages.subtitle') }}</p>
        </div>

        <div class="wp-row wp-cluster" style="display: flex; flex-direction: row; gap: 0.375rem; align-items: center;">
            <button wire:click="startCompose"
                class="btn btn--primary btn--sm"
                style="display: flex; align-items: center; gap: 0.375rem;">
                <svg style="width: 0.875rem; height: 0.875rem;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                </svg>
                {{ __('contact-messages.button_compose') }}
            </button>
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

    @if (session()->has('success'))
        <div class="wp-pill wp-pill--done" style="padding: 0.5rem 0.75rem; width: fit-content;">
            {{ session('success') }}
        </div>
    @endif

    <div class="wp-row" style="display: flex; flex-direction: row; gap: 1.25rem; align-items: start; width: 100%;">
        
        <div class="wp-stack" style="flex: 0 0 38%; width: 38%; --wp-stack-gap: 0.25rem; max-h: calc(100vh - 12rem); overflow-y: auto; padding-right: 0.25rem; margin-top: 0;">

            {{-- Bulk Selection Control Bar --}}
            <div class="wp-row" style="display: flex; flex-direction: row; justify-content: space-between; align-items: center; padding: 0.4rem 0.6rem; background: rgba(0,0,0,0.02); border-radius: var(--wp-radius, 6px); border: 1px solid rgba(0,0,0,0.04);">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <input
                        type="checkbox"
                        wire:click="toggleSelectAll"
                        :checked="{{ count(array_intersect($messages->pluck('id')->toArray(), $selectedMessageIds)) === $messages->count() && $messages->count() > 0 ? 'true' : 'false' }}"
                        style="cursor: pointer; width: 1rem; height: 1rem;"
                    >
                    <span class="wp-text-sm" style="font-size: 0.75rem; color: #64748b;">
                        @if(count($selectedMessageIds) > 0)
                            {{ count($selectedMessageIds) }} {{ __('contact-messages.selected') }}
                        @else
                            {{ __('contact-messages.select_all') }}
                        @endif
                    </span>
                </div>

                @if(count($selectedMessageIds) > 0)
                    <button
                        wire:click="deleteSelected"
                        wire:confirm="{{ __('contact-messages.confirm_delete') }}"
                        wire:loading.attr="disabled"
                        class="btn btn--danger btn--sm"
                        style="display: flex; align-items: center; gap: 0.25rem;"
                    >
                        <svg wire:loading wire:target="deleteSelected" style="width: 0.75rem; height: 0.75rem; animation: wp-spin 1s linear infinite;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg wire:loading.remove wire:target="deleteSelected" style="width: 0.875rem; height: 0.875rem;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>{{ __('contact-messages.button_delete') }}</span>
                    </button>
                @endif
            </div>

            @forelse($messages as $message)
                <div wire:click="selectMessage({{ $message->id }})"
                    class="wp-card wp-list-row {{ $selectedMessage && $selectedMessage->id === $message->id ? 'wp-list-row--active' : '' }}"
                    style="cursor: pointer; display: flex; flex-direction: row; gap: 0.5rem; padding: 0.4rem 0.6rem; border-radius: var(--wp-radius, 6px); transition: all 0.15s ease; border: 1px solid rgba(0,0,0,0.04); {{ $selectedMessage && $selectedMessage->id === $message->id ? 'border-color: var(--wp-accent); background-color: var(--wp-accent-soft);' : '' }} {{ $message->direction === 'inbound' && !$message->read_at ? 'border-left: 3px solid var(--wp-accent);' : '' }}">

                    {{-- Checkbox for bulk selection --}}
                    <div style="display: flex; align-items: center; flex-shrink: 0;">
                        <input
                            type="checkbox"
                            wire:model.live="selectedMessageIds"
                            value="{{ $message->id }}"
                            wire:click.stop
                            style="cursor: pointer; width: 1rem; height: 1rem;"
                        >
                    </div>

                    <div class="wp-stack" style="--wp-stack-gap: 0.15rem; flex: 1; min-width: 0;">
                        <div class="wp-row" style="display: flex; flex-direction: row; justify-content: space-between; align-items: center; width: 100%;">
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                @if($message->direction === 'inbound')
                                    <span class="wp-pill {{ !$message->read_at ? 'wp-pill--new' : 'wp-pill--progress' }}" style="font-size: 0.65rem; padding: 0.05rem 0.25rem;">In</span>
                                @else
                                    <span class="wp-pill wp-pill--closed" style="font-size: 0.65rem; padding: 0.05rem 0.25rem;">Out</span>
                                @endif
                                <span class="wp-text-sm wp-muted" style="font-size: 0.75rem;">{{ $message->created_at->format('d-m H:i') }}</span>
                            </div>
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
                            
                            @if($errors->has('reply'))
                                <span class="wp-error" style="display: block; font-size: 0.8rem;">{{ $errors->first('reply') }}</span>
                            @endif

                            <div class="wp-row" style="display: flex; flex-direction: row; justify-content: flex-end; width: 100%;">
                                <button wire:click="sendReply" wire:loading.attr="disabled" class="btn primary btn--sm" style="display: flex; align-items: center; gap: 0.375rem;">
                                    <svg wire:loading wire:target="sendReply" style="width: 0.875rem; height: 0.875rem; animation: wp-spin 1s linear infinite;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span wire:loading wire:target="sendReply">{{ __('contact-messages.button_sending') }}</span>
                                    <span wire:loading.remove wire:target="sendReply">{{ __('contact-messages.button_send') }}</span>
                                </button>
                            </div>
                        </div>
                    @endif

                </div>
            @elseif($isComposing)
                {{-- Compose New Message Form --}}
                <div class="wp-card wp-card-pad wp-stack" style="--wp-stack-gap: 1rem; border-radius: var(--wp-radius, 12px); padding: 1.25rem;">
                    <div style="border-bottom: 1px solid rgba(0,0,0,0.06); padding-bottom: 0.75rem;">
                        <h2 class="wp-section-title" style="margin: 0; font-size: 1.2rem;">{{ __('contact-messages.compose_title') }}</h2>
                    </div>

                    <div class="wp-stack" style="--wp-stack-gap: 0.75rem;">
                        <div class="wp-stack" style="--wp-stack-gap: 0.25rem;">
                            <label class="wp-label" style="font-weight: 600; font-size: 0.8rem; color: #475569;">{{ __('contact-messages.label_recipient_name') }}</label>
                            <input type="text" wire:model="newName" class="wp-input" placeholder="{{ __('contact-messages.placeholder_name') }}" style="width: 100%; border-radius: 6px; padding: 0.5rem 0.75rem; font-size: 0.9rem;">
                            @if($errors->has('newName'))
                                <span class="wp-error" style="display: block; font-size: 0.8rem;">{{ $errors->first('newName') }}</span>
                            @endif
                        </div>

                        <div class="wp-stack" style="--wp-stack-gap: 0.25rem;">
                            <label class="wp-label" style="font-weight: 600; font-size: 0.8rem; color: #475569;">{{ __('contact-messages.label_recipient_email') }}</label>
                            <input type="email" wire:model="newEmail" class="wp-input" placeholder="{{ __('contact-messages.placeholder_email') }}" style="width: 100%; border-radius: 6px; padding: 0.5rem 0.75rem; font-size: 0.9rem;">
                            @if($errors->has('newEmail'))
                                <span class="wp-error" style="display: block; font-size: 0.8rem;">{{ $errors->first('newEmail') }}</span>
                            @endif
                        </div>

                        <div class="wp-stack" style="--wp-stack-gap: 0.25rem;">
                            <label class="wp-label" style="font-weight: 600; font-size: 0.8rem; color: #475569;">{{ __('contact-messages.label_subject') }}</label>
                            <input type="text" wire:model="newSubject" class="wp-input" placeholder="{{ __('contact-messages.placeholder_subject') }}" style="width: 100%; border-radius: 6px; padding: 0.5rem 0.75rem; font-size: 0.9rem;">
                            @if($errors->has('newSubject'))
                                <span class="wp-error" style="display: block; font-size: 0.8rem;">{{ $errors->first('newSubject') }}</span>
                            @endif
                        </div>

                        <div class="wp-stack" style="--wp-stack-gap: 0.25rem;">
                            <label class="wp-label" style="font-weight: 600; font-size: 0.8rem; color: #475569;">{{ __('contact-messages.label_message') }}</label>
                            <textarea wire:model="newMessageBody" rows="8" class="wp-input" placeholder="{{ __('contact-messages.placeholder_message') }}" style="width: 100%; resize: vertical; border-radius: 6px; padding: 0.5rem 0.75rem; font-size: 0.9rem;"></textarea>
                            @if($errors->has('newMessageBody'))
                                <span class="wp-error" style="display: block; font-size: 0.8rem;">{{ $errors->first('newMessageBody') }}</span>
                            @endif
                        </div>
                    </div>

                    @if($errors->has('newMessage'))
                        <span class="wp-error" style="display: block; font-size: 0.8rem;">{{ $errors->first('newMessage') }}</span>
                    @endif

                    <div class="wp-row" style="display: flex; flex-direction: row; justify-content: flex-end; gap: 0.5rem; width: 100%; padding-top: 0.5rem; border-top: 1px solid rgba(0,0,0,0.06);">
                        <button wire:click="$set('isComposing', false)" class="btn btn--ghost btn--sm">
                            {{ __('contact-messages.button_cancel') }}
                        </button>
                        <button wire:click="sendNewMessage" wire:loading.attr="disabled" class="btn btn--primary btn--sm" style="display: flex; align-items: center; gap: 0.375rem;">
                            <svg wire:loading wire:target="sendNewMessage" style="width: 0.875rem; height: 0.875rem; animation: wp-spin 1s linear infinite;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading wire:target="sendNewMessage">{{ __('contact-messages.button_sending') }}</span>
                            <span wire:loading.remove wire:target="sendNewMessage">{{ __('contact-messages.button_send_message') }}</span>
                        </button>
                    </div>
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

    <style>
        @keyframes wp-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</div>