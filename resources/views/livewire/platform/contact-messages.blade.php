<div class="wp-stack">
    <x-wp-page-head-title
        icon="contact"
        title="Contactberichten"
        :subtitle="$this->unreadCount . ' ongelezen berichten'"
    />

    <!-- Centralized Premium Tab-Bar -->
    <div class="flex justify-center mb-6">
        <div class="bg-slate-100/80 dark:bg-slate-800/50 p-1 rounded-xl flex gap-1 max-w-md backdrop-blur-sm border border-slate-200/30 dark:border-slate-700/30">
            <button type="button" 
                    class="px-4 py-2 text-sm rounded-lg transition-all duration-200 {{ $filter === 'all' ? 'bg-white dark:bg-slate-700 shadow-sm font-medium text-slate-900 dark:text-white' : 'hover:text-slate-900 dark:hover:text-white text-slate-500 dark:text-slate-400' }}"
                    wire:click="setFilter('all')">
                Alle
            </button>
            <button type="button" 
                    class="px-4 py-2 text-sm rounded-lg transition-all duration-200 relative {{ $filter === 'inbound' ? 'bg-white dark:bg-slate-700 shadow-sm font-medium text-slate-900 dark:text-white' : 'hover:text-slate-900 dark:hover:text-white text-slate-500 dark:text-slate-400' }}"
                    wire:click="setFilter('inbound')">
                Inbox
                @if ($this->unreadCount > 0)
                    <span class="absolute -top-1 -right-1 h-5 w-5 bg-gradient-to-r from-teal-500 to-cyan-500 text-white text-xs rounded-full flex items-center justify-center shadow-sm">
                        {{ $this->unreadCount }}
                    </span>
                @endif
            </button>
            <button type="button" 
                    class="px-4 py-2 text-sm rounded-lg transition-all duration-200 {{ $filter === 'outbound' ? 'bg-white dark:bg-slate-700 shadow-sm font-medium text-slate-900 dark:text-white' : 'hover:text-slate-900 dark:hover:text-white text-slate-500 dark:text-slate-400' }}"
                    wire:click="setFilter('outbound')">
                Verzonden
            </button>
        </div>
    </div>

    <!-- Premium Split-Screen Layout -->
    <div class="flex gap-6 h-[calc(100vh-12rem)]">
        <!-- Left Column - Premium Messages List -->
        <div class="w-2/5 flex flex-col">
            <div class="flex-1 space-y-3 overflow-y-auto pr-2">
                @if ($messages->isEmpty())
                    <div class="flex-1 flex items-center justify-center h-64">
                        <div class="text-center">
                            <div class="w-16 h-16 mx-auto mb-4 bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-700 dark:to-slate-800 rounded-2xl flex items-center justify-center">
                                <svg class="w-8 h-8 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Geen berichten gevonden</p>
                        </div>
                    </div>
                @else
                    @foreach ($messages as $message)
                        <div class="bg-white/60 dark:bg-slate-900/40 backdrop-blur-md rounded-xl border border-slate-200/60 dark:border-slate-800/50 p-4 transition-all duration-200 hover:scale-[1.01] hover:shadow-lg cursor-pointer {{ $selectedMessage?->id === $message->id ? 'border-teal-500/50 bg-teal-50/10 dark:bg-teal-900/20 shadow-lg scale-[1.02]' : '' }}"
                             wire:click="selectMessage({{ $message->id }})">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center space-x-2 flex-1">
                                    @if (!$message->isRead() && $message->direction === 'inbound')
                                        <span class="w-2 h-2 bg-gradient-to-r from-teal-500 to-cyan-500 rounded-full inline-block mr-1 shadow-sm"></span>
                                    @endif
                                    <span class="wp-pill wp-pill--{{ $message->direction === 'inbound' ? 'primary' : 'secondary' }} text-xs">
                                        {{ $message->direction === 'inbound' ? 'In' : 'Out' }}
                                    </span>
                                </div>
                                <span class="text-xs text-slate-400 dark:text-slate-500">
                                    {{ $message->created_at->format('d-m H:i') }}
                                </span>
                            </div>
                            <div class="mb-2">
                                <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200 leading-tight">
                                    {{ $message->subject }}
                                </h4>
                            </div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $message->name }}
                            </div>
                        </div>
                    @endforeach

                    <div class="pt-4 border-t border-slate-200/30 dark:border-slate-700/30">
                        {{ $messages->links() }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Right Column - Premium Detail View -->
        <div class="w-3/5 flex flex-col">
            <div class="bg-white/80 dark:bg-slate-900/60 backdrop-blur-lg rounded-2xl border border-slate-200/60 dark:border-slate-800/50 p-6 shadow-xl flex-1 flex flex-col">
                @if ($selectedMessage)
                    <div class="flex-1 flex flex-col">
                        <!-- Message Header -->
                        <div class="border-b border-slate-200/50 dark:border-slate-700/50 pb-4 mb-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <span class="wp-pill wp-pill--{{ $selectedMessage->direction === 'inbound' ? 'primary' : 'secondary' }}">
                                        {{ $selectedMessage->direction === 'inbound' ? 'Inkomend' : 'Uitgaand' }}
                                    </span>
                                    <span class="text-xs text-slate-400 dark:text-slate-500">
                                        {{ $selectedMessage->created_at->format('d-m-Y H:i') }}
                                    </span>
                                </div>
                            </div>

                            <h3 class="text-xl font-semibold text-slate-800 dark:text-slate-100 mb-3">
                                {{ $selectedMessage->subject }}
                            </h3>
                            
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-teal-400 to-cyan-500 rounded-full flex items-center justify-center shadow-sm">
                                    <span class="text-sm font-medium text-white">
                                        {{ strtoupper(substr($selectedMessage->name, 0, 1)) }}
                                    </span>
                                </div>
                                <div>
                                    <div class="font-medium text-slate-800 dark:text-slate-200">
                                        {{ $selectedMessage->name }}
                                    </div>
                                    <div class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ $selectedMessage->email }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Message Content -->
                        <div class="flex-1 overflow-y-auto mb-4">
                            <div class="bg-slate-50/50 dark:bg-slate-800/30 rounded-xl p-4 border border-slate-200/30 dark:border-slate-700/30">
                                <div class="text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-wrap">
                                    {{ $selectedMessage->message }}
                                </div>
                            </div>
                        </div>

                        <!-- Integrated Reply Section -->
                        @if ($selectedMessage->direction === 'inbound')
                            <div class="border-t border-slate-200/50 dark:border-slate-700/50 pt-4">
                                @if (!$showReplyModal)
                                    <button type="button" 
                                            class="btn btn--primary w-full"
                                            wire:click="openReplyModal">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                        </svg>
                                        Beantwoorden
                                    </button>
                                @else
                                    <div class="space-y-3">
                                        <div class="text-sm text-slate-600 dark:text-slate-400 bg-slate-50/50 dark:bg-slate-800/30 p-3 rounded-lg">
                                            <div class="font-medium text-slate-700 dark:text-slate-300 mb-1">
                                                Aan: {{ $selectedMessage->name }}
                                            </div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ $selectedMessage->email }}
                                            </div>
                                        </div>

                                        <form wire:submit="sendReply" class="space-y-3">
                                            <div>
                                                <label class="wp-label" for="reply">Uw antwoord</label>
                                                <textarea id="reply" 
                                                          class="wp-input" 
                                                          wire:model="reply"
                                                          rows="4"
                                                          placeholder="Typ uw antwoord..."></textarea>
                                                @error('reply')
                                                    <div class="wp-error">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="flex gap-2">
                                                <button type="submit" class="btn btn--primary">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                                    </svg>
                                                    Versturen
                                                </button>
                                                <button type="button" class="btn btn--ghost" wire:click="closeReplyModal">
                                                    Annuleren
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @else
                    <!-- Premium Empty State -->
                    <div class="flex-1 flex items-center justify-center">
                        <div class="text-center max-w-sm">
                            <div class="w-20 h-20 mx-auto mb-6 bg-gradient-to-br from-slate-100 via-slate-200 to-slate-300 dark:from-slate-700 dark:via-slate-600 dark:to-slate-500 rounded-3xl flex items-center justify-center shadow-lg">
                                <svg class="w-10 h-10 text-slate-400 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-slate-800 dark:text-slate-100 mb-3">
                                Selecteer een bericht
                            </h3>
                            <p class="text-slate-500 dark:text-slate-400 text-sm leading-relaxed">
                                Kies een bericht uit de lijst om de details te bekijken en te beantwoorden
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

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
