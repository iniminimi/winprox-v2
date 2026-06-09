<div class="wp-stack">
    <x-wp-page-head-title
        icon="contact"
        title="Contactberichten"
        :subtitle="$this->unreadCount . ' ongelezen berichten'"
    />

    <!-- Filters -->
    <div class="wp-card wp-card-pad">
        <div class="wp-row wp-gap">
            <button type="button" 
                    class="btn {{ $filter === 'all' ? 'btn--primary' : 'btn--ghost' }}"
                    wire:click="setFilter('all')">
                Alle
            </button>
            <button type="button" 
                    class="btn {{ $filter === 'inbound' ? 'btn--primary' : 'btn--ghost' }}"
                    wire:click="setFilter('inbound')">
                Inbox
            </button>
            <button type="button" 
                    class="btn {{ $filter === 'outbound' ? 'btn--primary' : 'btn--ghost' }}"
                    wire:click="setFilter('outbound')">
                Verzonden
            </button>
        </div>
    </div>

    <div class="wp-grid wp-grid--2-1 wp-gap">
        <!-- Messages List -->
        <div class="wp-card wp-card-pad">
            @if ($messages->isEmpty())
                <p class="wp-muted">Geen berichten gevonden.</p>
            @else
                <div class="wp-list-plain wp-stack-tight">
                    @foreach ($messages as $message)
                        <div class="wp-list-row wp-clickable {{ $selectedMessage?->id === $message->id ? 'wp-list-row--active' : '' }} {{ !$message->isRead() && $message->direction === 'inbound' ? 'font-bold' : '' }}"
                             wire:click="selectMessage({{ $message->id }})">
                            <div class="wp-stack-tight">
                                <div class="wp-row wp-gap">
                                    <span class="wp-pill wp-pill--{{ $message->direction === 'inbound' ? 'primary' : 'secondary' }}">
                                        {{ $message->direction === 'inbound' ? 'In' : 'Out' }}
                                    </span>
                                    <span class="wp-muted text-sm">
                                        {{ $message->created_at->format('d-m H:i') }}
                                    </span>
                                </div>
                                <div>
                                    <strong>{{ $message->subject }}</strong>
                                </div>
                                <div class="wp-muted text-sm">
                                    {{ $message->name }} &lt;{{ $message->email }}&gt;
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{ $messages->links() }}
            @endif
        </div>

        <!-- Message Detail -->
        <div class="wp-card wp-card-pad">
            @if ($selectedMessage)
                <div class="wp-stack">
                    <div class="wp-row wp-gap">
                        <span class="wp-pill wp-pill--{{ $selectedMessage->direction === 'inbound' ? 'primary' : 'secondary' }}">
                            {{ $selectedMessage->direction === 'inbound' ? 'Inkomend' : 'Uitgaand' }}
                        </span>
                        <span class="wp-muted text-sm">
                            {{ $selectedMessage->created_at->format('d-m-Y H:i') }}
                        </span>
                    </div>

                    <div>
                        <h3 class="wp-section-title">{{ $selectedMessage->subject }}</h3>
                    </div>

                    <div class="wp-stack-tight">
                        <div>
                            <strong>{{ $selectedMessage->name }}</strong><br>
                            <span class="wp-muted">{{ $selectedMessage->email }}</span>
                        </div>
                    </div>

                    <div class="wp-message-content">
                        {!! nl2br(e($selectedMessage->message)) !!}
                    </div>

                    @if ($selectedMessage->direction === 'inbound')
                        <div class="wp-stack-tight">
                            <button type="button" 
                                    class="btn btn--primary"
                                    wire:click="openReplyModal">
                                Beantwoorden
                            </button>
                        </div>
                    @endif
                </div>
            @else
                <div class="wp-muted text-center py-8">
                    Selecteer een bericht om te lezen
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Reply Modal -->
@if ($showReplyModal)
    <div class="wp-modal" wire:ignore.self>
        <div class="wp-modal-content wp-card wp-card-pad wp-stack">
            <div class="wp-row wp-gap">
                <h3 class="wp-section-title">Beantwoorden</h3>
                <button type="button" class="btn btn--ghost btn--sm" wire:click="closeReplyModal">
                    ×
                </button>
            </div>

            @if ($selectedMessage)
                <div class="wp-muted text-sm">
                    Aan: {{ $selectedMessage->name }} &lt;{{ $selectedMessage->email }}&gt;<br>
                    Onderwerp: Re: {{ $selectedMessage->subject }}
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
    border-radius: 0.375rem;
    padding: 1rem;
    white-space: pre-wrap;
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
}

.wp-modal-content {
    min-width: 500px;
    max-width: 90vw;
    max-height: 90vh;
    overflow-y: auto;
}

.wp-list-row--active {
    background: #e3f2fd;
    border-color: #2196f3;
}
</style>
