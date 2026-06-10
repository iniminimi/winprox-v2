<div class="wp-stack wp-contact-page">
    <div class="wp-row wp-cluster wp-contact-header">
        <div class="wp-stack wp-contact-header-intro">
            <h1 class="wp-section-title wp-contact-header-title">{{ __('contact-messages.title') }}</h1>
            <p class="wp-text-sm wp-muted wp-contact-header-subtitle">{{ __('contact-messages.subtitle') }}</p>
        </div>

        <div class="wp-row wp-cluster wp-contact-toolbar">
            @can('accessPlatform', App\Models\User::class)
                <button wire:click="startCompose" class="btn btn--primary btn--sm wp-btn-with-icon">
                    <span class="wp-contact-compose-plus">+</span>
                    {{ __('contact-messages.button_compose') }}
                </button>
            @endcan
            <button wire:click="setFilter('inbound')"
                class="btn {{ $filter === 'inbound' ? 'btn--primary' : 'btn--ghost' }} btn--sm wp-btn-with-icon">
                {{ __('contact-messages.filter_inbound') }}
                @if($unreadCount > 0)
                    <span class="wp-pill wp-pill--new wp-contact-pill-sm">{{ $unreadCount }}</span>
                @endif
            </button>
            <button wire:click="setFilter('outbound')"
                class="btn {{ $filter === 'outbound' ? 'btn--primary' : 'btn--ghost' }} btn--sm">
                {{ __('contact-messages.filter_outbound') }}
            </button>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="wp-pill wp-pill--done wp-contact-flash">
            {{ session('success') }}
        </div>
    @endif

    <div class="wp-row wp-contact-layout">
        <div class="wp-stack wp-contact-sidebar wp-contact-sidebar-scroll">

            <div class="wp-row wp-contact-bulk-bar">
                <div class="wp-contact-bulk-select">
                    <input
                        type="checkbox"
                        wire:click="toggleSelectAll"
                        :checked="{{ count(array_intersect($messages->pluck('id')->toArray(), $selectedMessageIds)) === $messages->count() && $messages->count() > 0 ? 'true' : 'false' }}"
                        class="wp-contact-checkbox"
                    >
                    <span class="wp-text-sm wp-contact-meta-sm">
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
                        class="btn btn--danger btn--sm wp-btn-with-icon wp-btn-with-icon--tight"
                    >
                        <svg wire:loading wire:target="deleteSelected" class="wp-icon-spin-xs" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg wire:loading.remove wire:target="deleteSelected" class="wp-icon-btn" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>{{ __('contact-messages.button_delete') }}</span>
                    </button>
                @endif
            </div>

            @forelse($messages as $message)
                <div wire:click="selectMessage({{ $message->id }})"
                    class="wp-card wp-list-row wp-contact-row {{ $selectedMessage && $selectedMessage->id === $message->id ? 'wp-contact-row--active' : '' }} {{ $message->direction === 'inbound' && !$message->read_at ? 'wp-contact-row--unread' : '' }}">

                    <div class="wp-contact-bulk-select">
                        <input
                            type="checkbox"
                            wire:model.live="selectedMessageIds"
                            value="{{ $message->id }}"
                            wire:click.stop
                            class="wp-contact-checkbox"
                        >
                    </div>

                    <div class="wp-stack wp-contact-row-body">
                        <div class="wp-row wp-contact-row-meta">
                            @if($message->direction === 'inbound')
                                <span class="wp-pill {{ !$message->read_at ? 'wp-pill--new' : 'wp-pill--progress' }} wp-contact-pill-sm">{{ __('contact-messages.direction_in') }}</span>
                            @else
                                <span class="wp-pill wp-pill--closed wp-contact-pill-sm">{{ __('contact-messages.direction_out') }}</span>
                            @endif
                            <span class="wp-text-sm wp-muted wp-contact-meta-sm">{{ $message->created_at->format('d-m H:i') }}</span>
                        </div>

                        <div class="wp-text-body wp-contact-subject">
                            {{ Str::limit($message->subject, 45) }}
                        </div>

                        <div class="wp-text-sm wp-muted truncate wp-contact-sender">
                            {{ $message->name ?? $message->user?->name }}
                        </div>
                    </div>
                </div>
            @empty
                <div class="wp-card wp-card-pad wp-contact-empty-sidebar">
                    <p class="wp-text-body wp-muted">{{ __('contact-messages.no_messages') }}</p>
                </div>
            @endforelse

            @if($messages->hasPages())
                <div class="wp-contact-pagination">
                    {{ $messages->links() }}
                </div>
            @endif
        </div>

        <div class="wp-contact-main">
            @if($selectedMessage)
                <div class="wp-card wp-card-pad wp-stack wp-contact-panel">
                    <div class="wp-stack wp-contact-panel-header">
                        <div class="wp-row wp-contact-panel-title-row">
                            <h2 class="wp-section-title wp-contact-panel-title">{{ $selectedMessage->subject }}</h2>
                            <span class="wp-text-sm wp-muted wp-contact-timestamp">{{ $selectedMessage->created_at->format('d-m-Y H:i') }}</span>
                        </div>

                        <div class="wp-text-sm wp-muted wp-contact-from-line">
                            <span><strong>{{ __('contact-messages.label_from') }}:</strong> {{ $selectedMessage->name }} &lt;{{ $selectedMessage->email }}&gt;</span>
                            @if($selectedMessage->phone)
                                <span class="wp-contact-phone-line"><strong>{{ __('contact-messages.label_phone') }}:</strong> {{ $selectedMessage->phone }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="wp-text-body wp-contact-message-body">
                        {{ $selectedMessage->message }}
                    </div>

                    @if($selectedMessage->direction === 'inbound')
                        <div class="wp-stack wp-contact-reply-section">
                            <label class="wp-label wp-contact-form-label wp-contact-form-label--upper">
                                {{ __('contact-messages.title_reply') }}
                            </label>

                            <textarea wire:model="reply" rows="4" class="wp-input wp-contact-textarea"
                                placeholder="{{ __('contact-messages.placeholder_reply') }}"></textarea>

                            @if($errors->has('reply'))
                                <span class="wp-error wp-contact-error">{{ $errors->first('reply') }}</span>
                            @endif

                            <div class="wp-row wp-contact-actions-end">
                                <button wire:click="sendReply" wire:loading.attr="disabled" class="btn btn--primary btn--sm wp-btn-with-icon">
                                    <svg wire:loading wire:target="sendReply" class="wp-icon-spin-sm" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span wire:loading wire:target="sendReply">{{ __('contact-messages.button_sending') }}</span>
                                    <span wire:loading.remove wire:target="sendReply">{{ __('contact-messages.button_send') }}</span>
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            @elseif($isComposing)
                <div class="wp-card wp-card-pad wp-stack wp-contact-panel">
                    <div class="wp-contact-compose-header">
                        <h2 class="wp-section-title wp-contact-panel-title">{{ __('contact-messages.compose_title') }}</h2>
                    </div>

                    <div class="wp-stack wp-contact-compose-fields">
                        <div class="wp-stack wp-contact-field">
                            <label class="wp-label wp-contact-form-label">{{ __('contact-messages.label_email') }}</label>
                            <input type="email" wire:model="newEmail" class="wp-input wp-contact-input" placeholder="{{ __('contact-messages.placeholder_email') }}">
                            @if($errors->has('newEmail'))
                                <span class="wp-error wp-contact-error">{{ $errors->first('newEmail') }}</span>
                            @endif
                        </div>

                        <div class="wp-stack wp-contact-field">
                            <label class="wp-label wp-contact-form-label">{{ __('contact-messages.label_subject') }}</label>
                            <input type="text" wire:model="newSubject" class="wp-input wp-contact-input" placeholder="{{ __('contact-messages.placeholder_subject') }}">
                            @if($errors->has('newSubject'))
                                <span class="wp-error wp-contact-error">{{ $errors->first('newSubject') }}</span>
                            @endif
                        </div>

                        <div class="wp-stack wp-contact-field">
                            <label class="wp-label wp-contact-form-label">{{ __('contact-messages.label_message') }}</label>
                            <textarea wire:model="newMessageBody" rows="10" class="wp-input wp-contact-textarea" placeholder="{{ __('contact-messages.placeholder_message') }}"></textarea>
                            @if($errors->has('newMessageBody'))
                                <span class="wp-error wp-contact-error">{{ $errors->first('newMessageBody') }}</span>
                            @endif
                        </div>
                    </div>

                    @if($errors->has('newMessage'))
                        <span class="wp-error wp-contact-error">{{ $errors->first('newMessage') }}</span>
                    @endif

                    <div class="wp-row wp-contact-actions-end wp-contact-actions-end--gap">
                        <button wire:click="$set('isComposing', false)" class="btn btn--ghost btn--sm">
                            {{ __('contact-messages.button_cancel') }}
                        </button>
                        <button wire:click="sendNewMessage" wire:loading.attr="disabled" class="btn btn--primary btn--sm wp-btn-with-icon">
                            <svg wire:loading wire:target="sendNewMessage" class="wp-icon-spin-sm" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading wire:target="sendNewMessage">{{ __('contact-messages.button_sending') }}</span>
                            <span wire:loading.remove wire:target="sendNewMessage">{{ __('contact-messages.button_send_message') }}</span>
                        </button>
                    </div>
                </div>
            @else
                <div class="wp-card wp-card-pad wp-contact-empty-main">
                    <div class="wp-stack wp-contact-empty-main-inner">
                        <h3 class="wp-section-title wp-contact-empty-main-title">{{ __('contact-messages.empty_title') }}</h3>
                        <p class="wp-text-body wp-muted wp-contact-empty-main-body">
                            {{ __('contact-messages.empty_body') }}
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
