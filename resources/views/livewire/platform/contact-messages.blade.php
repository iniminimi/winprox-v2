<div class="wp-stack">
    <div class="wp-row wp-gap">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
                <span class="p-2 rounded-xl bg-teal-500/10 text-teal-600 dark:text-teal-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 002-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </span>
                {{ __('contact-messages.title') }}
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('contact-messages.subtitle') }}
            </p>
        </div>

        <div class="wp-row wp-gap">
            <button wire:click="setFilter('all')" 
                class="btn {{ $filter === 'all' ? 'btn--primary' : 'btn--ghost' }} btn--sm">
                {{ __('contact-messages.filter_all') }}
            </button>
            <button wire:click="setFilter('inbound')" 
                class="btn {{ $filter === 'inbound' ? 'btn--primary' : 'btn--ghost' }} btn--sm flex items-center gap-1.5">
                {{ __('contact-messages.filter_inbox') }}
                @if($unreadCount > 0)
                    <span class="wp-badge wp-badge-secondary">{{ $unreadCount }}</span>
                @endif
            </button>
            <button wire:click="setFilter('outbound')" 
                class="btn {{ $filter === 'outbound' ? 'btn--primary' : 'btn--ghost' }} btn--sm">
                {{ __('contact-messages.filter_outbound') }}
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <div class="lg:col-span-5">
            <div class="wp-card wp-card-pad">
                <div class="wp-list-plain wp-stack-tight max-h-[calc(100vh-14rem)] overflow-y-auto">
                    @forelse($messages as $message)
                        <div wire:click="selectMessage({{ $message->id }})"
                            class="wp-list-row wp-clickable {{ $selectedMessage && $selectedMessage->id === $message->id ? 'wp-list-row--active' : '' }} {{ !$message->read_at && $message->direction === 'inbound' ? 'font-semibold' : '' }}">
                            
                            <div class="wp-stack-tight">
                                <div class="wp-row wp-gap">
                                    @if($message->direction === 'inbound' && !$message->read_at)
                                        <span class="w-2 h-2 rounded-full bg-teal-500 shrink-0"></span>
                                    @endif
                                    <span class="wp-pill wp-pill--{{ $message->direction === 'inbound' ? 'new' : 'closed' }} text-xs">
                                        {{ $message->direction === 'inbound' ? __('contact-messages.direction_in') : __('contact-messages.direction_out') }}
                                    </span>
                                    <span class="wp-muted text-xs">
                                        {{ $message->created_at->format('d-m H:i') }}
                                    </span>
                                </div>
                                
                                <div>
                                    <strong>{{ $message->subject }}</strong>
                                </div>
                                
                                <div class="wp-muted text-xs">
                                    {{ $message->name ?? $message->user?->name }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8">
                            <p class="wp-muted">{{ __('contact-messages.no_messages') }}</p>
                        </div>
                    @endforelse

                    {{ $messages->links() }}
                </div>
            </div>
        </div>

        <div class="lg:col-span-7">
            @if($selectedMessage)
                <div class="wp-card wp-card-pad">
                    <div class="wp-stack">
                        <div class="wp-row wp-gap">
                            <div>
                                <h3 class="wp-section-title">{{ $selectedMessage->subject }}</h3>
                                <div class="wp-stack-tight">
                                    <div class="wp-muted text-xs">
                                        <strong>{{ __('contact-messages.from') }}:</strong> 
                                        {{ $selectedMessage->name }} &lt;{{ $selectedMessage->email }}&gt;
                                    </div>
                                    @if($selectedMessage->phone)
                                        <div class="wp-muted text-xs">
                                            <strong>{{ __('contact-messages.phone') }}:</strong> {{ $selectedMessage->phone }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <span class="wp-pill wp-pill--closed text-xs">
                                {{ $selectedMessage->created_at->format('d-m-Y H:i') }}
                            </span>
                        </div>

                        <div class="wp-message-content">
                            {!! nl2br(e($selectedMessage->message)) !!}
                        </div>

                        @if($selectedMessage->direction === 'inbound')
                            <div class="wp-stack-tight">
                                <h4 class="wp-section-title">{{ __('contact-messages.reply_section') }}</h4>
                                <form wire:submit="sendReply" class="wp-stack-tight">
                                    <div>
                                        <label class="wp-label" for="reply">{{ __('contact-messages.reply_placeholder') }}</label>
                                        <textarea id="reply" 
                                                  class="wp-input" 
                                                  wire:model="reply"
                                                  rows="4"
                                                  placeholder="{{ __('contact-messages.reply_placeholder') }}"></textarea>
                                        @error('reply')
                                            <div class="wp-error">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="wp-row wp-gap">
                                        <button type="submit" class="btn btn--primary">
                                            {{ __('contact-messages.send_reply') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="wp-card wp-card-pad text-center">
                    <div class="wp-muted text-center py-8">
                        <h3 class="wp-section-title">{{ __('contact-messages.no_message_selected') }}</h3>
                        <p class="wp-muted">
                            {{ __('contact-messages.select_message_hint') }}
                        </p>
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>