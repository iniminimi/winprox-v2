<div class="wp-stack">
    <x-wp-page-head-title
        icon="contact"
        title="Contactberichten"
        :subtitle="$this->unreadCount . ' ongelezen berichten'"
    />

    <!-- Modern Filter Tabs -->
    <div class="wp-card wp-card-pad">
        <div class="flex bg-gray-50 dark:bg-gray-800 rounded-lg p-1">
            <button type="button" 
                    class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 {{ $filter === 'all' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' }}"
                    wire:click="setFilter('all')">
                Alle
            </button>
            <button type="button" 
                    class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 relative {{ $filter === 'inbound' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' }}"
                    wire:click="setFilter('inbound')">
                Inbox
                @if ($this->unreadCount > 0)
                    <span class="absolute -top-1 -right-1 h-5 w-5 bg-blue-500 text-white text-xs rounded-full flex items-center justify-center">
                        {{ $this->unreadCount }}
                    </span>
                @endif
            </button>
            <button type="button" 
                    class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 {{ $filter === 'outbound' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' }}"
                    wire:click="setFilter('outbound')">
                Verzonden
            </button>
        </div>
    </div>

    <!-- Split-Screen Layout -->
    <div class="flex gap-6 h-[calc(100vh-12rem)]">
        <!-- Left Column - Messages List (2/5 width) -->
        <div class="w-2/5 flex flex-col">
            <div class="wp-card wp-card-pad flex-1 flex flex-col overflow-hidden">
                @if ($messages->isEmpty())
                    <div class="flex-1 flex items-center justify-center">
                        <div class="text-center">
                            <div class="text-gray-400 dark:text-gray-500 mb-2">
                                <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <p class="wp-muted">Geen berichten gevonden.</p>
                        </div>
                    </div>
                @else
                    <div class="flex-1 overflow-y-auto space-y-3">
                        @foreach ($messages as $message)
                            <div class="rounded-xl border p-4 cursor-pointer transition-all duration-200 hover:shadow-md hover:border-blue-200 dark:hover:border-blue-800 {{ $selectedMessage?->id === $message->id ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 shadow-md' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800' }} {{ !$message->isRead() && $message->direction === 'inbound' ? 'font-semibold' : '' }}"
                                 wire:click="selectMessage({{ $message->id }})">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex items-center space-x-2">
                                        <span class="wp-pill wp-pill--{{ $message->direction === 'inbound' ? 'primary' : 'secondary' }} text-xs">
                                            {{ $message->direction === 'inbound' ? 'In' : 'Out' }}
                                        </span>
                                        @if (!$message->isRead() && $message->direction === 'inbound')
                                            <span class="h-2 w-2 bg-blue-500 rounded-full"></span>
                                        @endif
                                    </div>
                                    <span class="wp-muted text-xs">
                                        {{ $message->created_at->format('d-m H:i') }}
                                    </span>
                                </div>
                                <div class="mb-1">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {{ $message->subject }}
                                    </h4>
                                </div>
                                <div class="wp-muted text-xs">
                                    {{ $message->name }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $messages->links() }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Right Column - Message Detail (3/5 width) -->
        <div class="w-3/5 flex flex-col">
            <div class="wp-card wp-card-pad flex-1 flex flex-col">
                @if ($selectedMessage)
                    <div class="flex-1 flex flex-col">
                        <!-- Message Header -->
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-4 mb-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <span class="wp-pill wp-pill--{{ $selectedMessage->direction === 'inbound' ? 'primary' : 'secondary' }}">
                                        {{ $selectedMessage->direction === 'inbound' ? 'Inkomend' : 'Uitgaand' }}
                                    </span>
                                    <span class="wp-muted text-sm">
                                        {{ $selectedMessage->created_at->format('d-m-Y H:i') }}
                                    </span>
                                </div>
                                @if ($selectedMessage->direction === 'inbound')
                                    <button type="button" 
                                            class="btn btn--primary btn--sm"
                                            wire:click="openReplyModal">
                                        Beantwoorden
                                    </button>
                                @endif
                            </div>

                            <h3 class="wp-section-title mb-2">{{ $selectedMessage->subject }}</h3>
                            
                            <div class="flex items-center space-x-2">
                                <div class="w-8 h-8 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                                        {{ strtoupper(substr($selectedMessage->name, 0, 1)) }}
                                    </span>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ $selectedMessage->name }}
                                    </div>
                                    <div class="wp-muted text-sm">
                                        {{ $selectedMessage->email }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Message Content -->
                        <div class="flex-1 overflow-y-auto">
                            <div class="wp-message-content">
                                {!! nl2br(e($selectedMessage->message)) !!}
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Empty State -->
                    <div class="flex-1 flex items-center justify-center">
                        <div class="text-center">
                            <div class="text-gray-400 dark:text-gray-500 mb-4">
                                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                                Selecteer een bericht
                            </h3>
                            <p class="wp-muted">
                                Kies een bericht uit de lijst om de details te bekijken
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Reply Modal -->
@if ($showReplyModal)
    <div class="wp-modal" wire:ignore.self>
        <div class="wp-modal-content wp-card wp-card-pad wp-stack max-w-2xl">
            <div class="wp-row wp-gap">
                <h3 class="wp-section-title">Beantwoorden</h3>
                <button type="button" class="btn btn--ghost btn--sm" wire:click="closeReplyModal">
                    ×
                </button>
            </div>

            @if ($selectedMessage)
                <div class="wp-muted text-sm bg-gray-50 dark:bg-gray-800 p-3 rounded-lg">
                    <div class="font-medium text-gray-900 dark:text-white mb-1">
                        Aan: {{ $selectedMessage->name }}
                    </div>
                    <div class="text-xs">
                        {{ $selectedMessage->email }}
                    </div>
                    <div class="text-xs mt-1">
                        Onderwerp: Re: {{ $selectedMessage->subject }}
                    </div>
                </div>
            @endif

            <form wire:submit="sendReply">
                <div class="wp-stack-tight">
                    <label class="wp-label" for="reply">Uw antwoord</label>
                    <textarea id="reply" 
                              class="wp-input" 
                              wire:model="reply"
                              rows="8"
                              placeholder="Typ uw antwoord..."></textarea>
                    @error('reply')
                        <div class="wp-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="wp-row wp-gap">
                    <button type="submit" class="btn btn--primary">
                        Versturen
                    </button>
                    <button type="button" class="btn btn--ghost" wire:click="closeReplyModal">
                        Annuleren
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

<style>
.wp-message-content {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 0.5rem;
    padding: 1.5rem;
    white-space: pre-wrap;
    line-height: 1.6;
    font-size: 0.95rem;
}

.dark .wp-message-content {
    background: #1f2937;
    border-color: #374151;
    color: #f3f4f6;
}

.wp-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    backdrop-filter: blur(4px);
}

.wp-modal-content {
    min-width: 500px;
    max-width: 90vw;
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 1rem;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

/* Custom scrollbar for message list */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.dark .overflow-y-auto::-webkit-scrollbar-thumb {
    background: #475569;
}

.dark .overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}
</style>
