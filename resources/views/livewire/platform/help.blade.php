<div class="wp-stack">
    <div class="wp-stack-tight">
        <h1 class="wp-page-title">{{ __('platform.help.title') }}</h1>
        <p class="wp-muted">{{ __('platform.help.subtitle') }}</p>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-row">
            <h2 class="wp-section-title">{{ __('platform.help.unanswered_title') }}</h2>
        </div>
        @if ($unanswered->isEmpty())
            <p class="wp-muted">{{ __('platform.help.unanswered_empty') }}</p>
        @else
            <ul class="wp-list-plain wp-stack-tight">
                @foreach ($unanswered as $row)
                    <li class="wp-list-row" wire:key="unanswered-{{ $row->id }}">
                        <div class="wp-grow">
                            <p class="wp-text-body">{{ $row->question }}</p>
                            <p class="wp-muted wp-text-sm">
                                {{ $tenants[$row->tenant_id] ?? '—' }}
                                · {{ $row->user?->email ?? '—' }}
                                · {{ $row->locale }}
                                · {{ $row->created_at?->format('d-m-Y H:i') }}
                            </p>
                        </div>
                        <button type="button" class="btn btn--ghost btn--sm"
                                wire:click="dismissUnanswered({{ $row->id }})"
                                wire:confirm="{{ __('platform.help.dismiss_confirm') }}">
                            {{ __('platform.help.dismiss') }}
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-row">
            <h2 class="wp-section-title">{{ __('platform.help.kb_title') }}</h2>
            <button type="button" class="btn btn--primary btn--sm" wire:click="openCreateKb">
                {{ __('platform.help.kb_add') }}
            </button>
        </div>
        @if ($kbEntries->isEmpty())
            <p class="wp-muted">{{ __('platform.help.kb_empty') }}</p>
        @else
            <ul class="wp-list-plain wp-stack-tight">
                @foreach ($kbEntries as $entry)
                    <li class="wp-list-row" wire:key="kb-{{ $entry->id }}">
                        <div class="wp-grow">
                            <p class="wp-text-body">
                                <strong>{{ $entry->match_key }}</strong>
                                <span class="wp-muted">({{ $entry->locale }})</span>
                                @unless ($entry->is_active)
                                    <span class="wp-pill wp-pill--closed">{{ __('platform.help.inactive') }}</span>
                                @endunless
                            </p>
                            <p class="wp-muted wp-text-sm">{{ \Illuminate\Support\Str::limit($entry->answer, 120) }}</p>
                        </div>
                        <div class="wp-cluster">
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditKb({{ $entry->id }})">
                                {{ __('common.button.edit') }}
                            </button>
                            <button type="button" class="btn btn--ghost btn--sm"
                                    wire:click="deleteKb({{ $entry->id }})"
                                    wire:confirm="{{ __('platform.help.kb_delete_confirm') }}">
                                {{ __('common.button.delete') }}
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($showKbModal)
        <div class="wp-modal">
            <form wire:submit="saveKb" class="wp-card wp-card-pad wp-stack wp-modal-card wp-modal-card--wide">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">
                        {{ $editingKbId ? __('platform.help.kb_edit') : __('platform.help.kb_add') }}
                    </h2>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="closeKbModal">{{ __('common.button.cancel') }}</button>
                </div>

                <div class="wp-filter-bar">
                    <div class="wp-field">
                        <label class="wp-label" for="kbLocale">{{ __('platform.help.kb_locale') }}</label>
                        <input id="kbLocale" type="text" class="wp-input" wire:model="kbLocale" placeholder="nl, en, *">
                        @error('kbLocale') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="kbMatchKey">{{ __('platform.help.kb_match_key') }}</label>
                        <input id="kbMatchKey" type="text" class="wp-input" wire:model="kbMatchKey">
                        @error('kbMatchKey') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="wp-field">
                    <label class="wp-label" for="kbPatterns">{{ __('platform.help.kb_patterns') }}</label>
                    <textarea id="kbPatterns" class="wp-textarea" wire:model="kbPatterns" rows="4"></textarea>
                    @error('kbPatterns') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-field">
                    <label class="wp-label" for="kbAnswer">{{ __('platform.help.kb_answer') }}</label>
                    <textarea id="kbAnswer" class="wp-textarea" wire:model="kbAnswer" rows="5"></textarea>
                    @error('kbAnswer') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <label class="wp-check">
                    <input type="checkbox" wire:model="kbIsActive">
                    {{ __('platform.help.kb_active') }}
                </label>

                <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
            </form>
        </div>
    @endif
</div>
