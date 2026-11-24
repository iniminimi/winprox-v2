<x-wp-page-head-title
    variant="portal"
    icon="help"
    :title="__('ops.menu.help')"
    :subtitle="__('platform.help.unanswered_title')"
/>

<div class="wp-card wp-card-pad wp-stack">
    @if ($answeringQuestionId)
        <form wire:submit="saveAnswer" class="wp-stack">
            <p class="wp-muted wp-text-sm">{{ __('platform.help.answer_context') }}</p>
            <p class="wp-text-body">{{ $answeringQuestionText }}</p>

            <div>
                <label class="wp-label" for="ops-kb-locale">{{ __('platform.help.kb_locale') }}</label>
                <input id="ops-kb-locale" type="text" class="wp-input" wire:model="kbLocale" autocomplete="off">
                @error('kbLocale') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="wp-label" for="ops-kb-key">{{ __('platform.help.kb_match_key') }}</label>
                <input id="ops-kb-key" type="text" class="wp-input" wire:model="kbMatchKey" autocomplete="off">
                @error('kbMatchKey') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="wp-label" for="ops-kb-patterns">{{ __('platform.help.kb_patterns') }}</label>
                <textarea id="ops-kb-patterns" class="wp-input" rows="3" wire:model="kbPatterns"></textarea>
                @error('kbPatterns') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="wp-label" for="ops-kb-answer">{{ __('platform.help.kb_answer') }}</label>
                <textarea id="ops-kb-answer" class="wp-input" rows="5" wire:model="kbAnswer"></textarea>
                @error('kbAnswer') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div class="wp-cluster wp-cluster--wrap">
                <button type="submit" class="btn btn--primary">{{ __('platform.help.answer') }}</button>
                <button type="button" class="btn btn--ghost" wire:click="closeAnswerQuestion">{{ __('common.button.cancel') }}</button>
            </div>
        </form>
    @elseif ($unanswered->isEmpty())
        <p class="wp-muted">{{ __('platform.help.unanswered_empty') }}</p>
    @else
        <ul class="wp-list-plain wp-stack-tight">
            @foreach ($unanswered as $row)
                <li class="wp-list-row" wire:key="ops-unanswered-{{ $row->id }}">
                    <div class="wp-grow">
                        <p class="wp-text-body">{{ $row->question }}</p>
                        <p class="wp-muted wp-text-sm">
                            {{ $row->tenant?->name ?? '—' }}
                            · {{ $row->user?->email ?? '—' }}
                            · {{ $row->locale }}
                            · {{ $row->created_at?->format('d-m-Y H:i') }}
                        </p>
                    </div>
                    <div class="wp-cluster wp-cluster--wrap">
                        <button type="button" class="btn btn--primary btn--sm"
                                wire:click="openAnswerQuestion({{ $row->id }})">
                            {{ __('platform.help.answer') }}
                        </button>
                        <button type="button" class="btn btn--ghost btn--sm"
                                wire:click="dismissUnanswered({{ $row->id }})"
                                wire:confirm="{{ __('platform.help.dismiss_confirm') }}">
                            {{ __('platform.help.dismiss') }}
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
