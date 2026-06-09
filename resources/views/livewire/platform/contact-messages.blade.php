<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
                <span class="p-2 rounded-xl bg-teal-500/10 text-teal-600 dark:text-teal-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 002-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </span>
                Contactberichten
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Beheer binnenkomende vragen en platformberichten.
            </p>
        </div>

        <div class="p-1 bg-slate-100 dark:bg-slate-800/80 rounded-xl flex items-center gap-1 shadow-inner">
            <button wire:click="setFilter('all')" 
                class="px-4 py-1.5 text-xs font-medium rounded-lg transition-all duration-200 {{ $filter === 'all' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-200' }}">
                Alle
            </button>
            <button wire:click="setFilter('inbound')" 
                class="px-4 py-1.5 text-xs font-medium rounded-lg transition-all duration-200 flex items-center gap-1.5 {{ $filter === 'inbound' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-200' }}">
                Inbox
                @if($unreadCount > 0)
                    <span class="px-1.5 py-0.5 text-[10px] font-bold bg-teal-500 text-white rounded-full">{{ $unreadCount }}</span>
                @endif
            </button>
            <button wire:click="setFilter('outbound')" 
                class="px-4 py-1.5 text-xs font-medium rounded-lg transition-all duration-200 {{ $filter === 'outbound' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-200' }}">
                Verzonden
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <div class="lg:col-span-5 space-y-3 max-h-[calc(100vh-14rem)] overflow-y-auto pr-2">
            @forelse($messages as $message)
                <div wire:click="selectMessage({{ $message->id }})"
                    class="group relative p-4 rounded-xl transition-all duration-200 cursor-pointer border backdrop-blur-md 
                    {{ $selectedMessage && $selectedMessage->id === $message->id 
                        ? 'bg-teal-500/5 border-teal-500/40 shadow-sm ring-1 ring-teal-500/30' 
                        : 'bg-white/70 dark:bg-slate-900/40 border-slate-200/80 dark:border-slate-800/60 hover:bg-white dark:hover:bg-slate-900 shadow-xs' }}">
                    
                    <div class="flex items-start justify-between gap-4 mb-1">
                        <div class="flex items-center gap-2 min-w-0">
                            @if($message->direction === 'inbound' && !$message->read_at)
                                <span class="w-2 h-2 rounded-full bg-teal-500 shrink-0 shadow-[0_0_8px_rgba(20,184,166,0.6)]"></span>
                            @endif
                            <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">
                                {{ $message->direction === 'inbound' ? 'In' : 'Uit' }}
                            </span>
                        </div>
                        <span class="text-[11px] text-slate-400 whitespace-nowrap">
                            {{ $message->created_at->format('d-m H:i') }}
                        </span>
                    </div>

                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200 truncate group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors">
                        {{ $message->subject }}
                    </h3>
                    
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">
                        {{ $message->name ?? $message->user?->name }}
                    </p>
                </div>
            @empty
                <div class="p-8 text-center bg-white/40 dark:bg-slate-900/20 rounded-xl border border-dashed border-slate-200 dark:border-slate-800">
                    <p class="text-sm text-slate-500">Geen berichten gevonden.</p>
                </div>
            @endforelse

            <div class="pt-2">
                {{ $messages->links() }}
            </div>
        </div>

        <div class="lg:col-span-7 h-full">
            @if($selectedMessage)
                <div class="bg-white/80 dark:bg-slate-900/60 backdrop-blur-lg rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm overflow-hidden flex flex-col">
                    
                    <div class="p-6 border-b border-slate-100 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/20">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $selectedMessage->subject }}</h2>
                                <div class="mt-2 flex flex-col gap-1 text-xs text-slate-500 dark:text-slate-400">
                                    <p>
                                        <span class="font-medium text-slate-700 dark:text-slate-300">Van:</span> 
                                        {{ $selectedMessage->name }} &lt;{{ $selectedMessage->email }}&gt;
                                    </p>
                                    @if($selectedMessage->phone)
                                        <p><span class="font-medium text-slate-700 dark:text-slate-300">Tel:</span> {{ $selectedMessage->phone }}</p>
                                    @endif
                                </div>
                            </div>
                            <span class="text-xs text-slate-400 bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-md font-medium">
                                {{ $selectedMessage->created_at->format('d-m-Y H:i') }}
                            </span>
                        </div>
                    </div>

                    <div class="p-6 text-sm text-slate-700 dark:text-slate-300 leading-relaxed max-h-[24rem] overflow-y-auto whitespace-pre-wrap">
                        {{ $selectedMessage->message }}
                    </div>

                    @if($selectedMessage->direction === 'inbound')
                        <div class="p-6 bg-slate-50/50 dark:bg-slate-900/40 border-t border-slate-100 dark:border-slate-800/80">
                            <h4 class="text-xs font-semibold text-slate-900 dark:text-white uppercase tracking-wider mb-3">Antwoord opstellen</h4>
                            <div class="space-y-3">
                                <textarea wire:model="reply" rows="4" 
                                    placeholder="Typ hier uw professionele reactie..."
                                    class="w-full text-sm p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-900 dark:text-white shadow-xs focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 focus:outline-hidden transition-all placeholder:text-slate-400"></textarea>
                                
                                @error('reply') 
                                    <p class="text-xs font-medium text-red-500">{{ $message }}</p> 
                                @enderror

                                <div class="flex justify-end">
                                    <button wire:click="sendReply" 
                                        class="px-5 py-2 text-sm font-semibold text-white bg-teal-600 hover:bg-teal-500 rounded-xl shadow-xs transition-all duration-150 flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                        </svg>
                                        Antwoord Verzenden
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <div class="h-full min-h-[30rem] bg-white/40 dark:bg-slate-900/10 backdrop-blur-md rounded-2xl border border-dashed border-slate-200 dark:border-slate-800/80 p-12 flex flex-col items-center justify-center text-center">
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 shadow-xs text-slate-400 mb-4">
                        <svg class="w-10 h-10 stroke-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25H4.5a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5H4.5a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0l-7.5-4.615a2.25 2.25 0 01-1.07-1.916V6.75"/>
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Geen bericht geselecteerd</h3>
                    <p class="mt-1 text-sm text-slate-500 max-w-xs">
                        Kies een bericht uit de linkerkolom om de volledige inhoud te bekijken en direct te beantwoorden.
                    </p>
                </div>
            @endif
        </div>

    </div>
</div>