<div class="wp-help-chat">
    <div class="wp-help-chat-messages" role="log" aria-live="polite">
        @foreach ($messages as $message)
            <div class="wp-help-chat-bubble wp-help-chat-bubble--{{ $message['role'] }}" wire:key="msg-{{ $message['id'] }}">
                <p>{!! nl2br(e($message['content'])) !!}</p>
            </div>
        @endforeach
    </div>

    <form
        class="wp-help-chat-form"
        wire:submit="send"
        x-data
        @keydown.enter="
            if ($event.target.matches('textarea') && ! $event.shiftKey) {
                $event.preventDefault();
                $el.requestSubmit();
            }
        "
    >
        <label class="sr-only" for="help-chat-draft">{{ __('help.input_label') }}</label>
        <textarea id="help-chat-draft"
                  class="wp-input"
                  rows="2"
                  wire:model="draft"
                  placeholder="{{ __('help.input_placeholder') }}"></textarea>
        <div class="wp-cluster">
            <button type="submit" class="btn btn--primary btn--sm">{{ __('help.send') }}</button>
            @if ($escalationQuestion)
                <button type="button" class="btn btn--ghost btn--sm" wire:click="escalateToHelpdesk">
                    {{ __('help.escalate') }}
                </button>
            @endif
        </div>
    </form>
</div>
