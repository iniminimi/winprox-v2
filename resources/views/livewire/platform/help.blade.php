<div class="wp-stack">
    <x-wp-page-head-title
        icon="faq"
        :title="__('platform.help.title')"
        help-page="platform.help"
        :subtitle="__('platform.help.subtitle')"
    />

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
                        <div class="wp-cluster">
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
                                <span class="wp-muted">({{ $entry->original_language }})</span>
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
                        @if ($editingKbId)
                            {{ __('platform.help.kb_edit') }}
                        @elseif ($answeringQuestionId)
                            {{ __('platform.help.answer_question') }}
                        @else
                            {{ __('platform.help.kb_add') }}
                        @endif
                    </h2>
                    <x-wp-modal-close wire:click="closeKbModal" />
                </div>

                @if ($answeringQuestionText)
                    <p class="wp-muted wp-text-sm">
                        <strong>{{ __('platform.help.answer_context') }}:</strong>
                        {{ $answeringQuestionText }}
                    </p>
                @endif

                <div class="wp-filter-bar">
                    <div class="wp-field">
                        <label class="wp-label" for="kbLocale">{{ __('platform.help.kb_locale') }}</label>
                        <input id="kbLocale" type="text" class="wp-input" wire:model="kbLocale" placeholder="nl, en, …">
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

                @if ($editingKbId)
                    <div class="wp-stack-tight">
                        <h3 class="wp-section-title">{{ __('platform.help.kb_translations_title') }}</h3>
                        <p class="wp-muted wp-text-sm">{{ __('platform.help.kb_translations_hint') }}</p>

                        @if ($kbTranslations === [])
                            <p class="wp-muted wp-text-sm">{{ __('platform.help.kb_translations_empty') }}</p>
                        @else
                            <ul class="wp-list-plain wp-stack-tight">
                                @foreach ($kbTranslations as $translation)
                                    @php
                                        $statusValue = $translation->status->value;
                                        $statusLabel = match ($statusValue) {
                                            'completed' => __('platform.help.kb_translation_status_completed'),
                                            'failed' => __('platform.help.kb_translation_status_failed'),
                                            default => __('platform.help.kb_translation_status_pending'),
                                        };
                                        $statusPill = match ($statusValue) {
                                            'completed' => 'wp-pill wp-pill--done',
                                            'failed' => 'wp-pill wp-pill--closed',
                                            default => 'wp-pill wp-pill--new',
                                        };
                                    @endphp
                                    <li class="wp-card wp-card-pad wp-stack-tight" wire:key="kb-translation-{{ $translation->id }}">
                                        <div class="wp-row">
                                            <strong>{{ strtoupper($translation->locale) }}</strong>
                                            <span class="{{ $statusPill }}">{{ $statusLabel }}</span>
                                        </div>
                                        @if (filled($translation->patterns))
                                            <div class="wp-field">
                                                <p class="wp-label">{{ __('platform.help.kb_patterns') }}</p>
                                                <p class="wp-text-body wp-text-sm">{!! nl2br(e(implode("\n", $translation->patterns ?? []))) !!}</p>
                                            </div>
                                        @endif
                                        @if (filled($translation->answer))
                                            <div class="wp-field">
                                                <p class="wp-label">{{ __('platform.help.kb_answer') }}</p>
                                                <p class="wp-text-body wp-text-sm">{!! nl2br(e($translation->answer)) !!}</p>
                                            </div>
                                        @elseif ($statusValue === 'pending')
                                            <p class="wp-muted wp-text-sm">{{ __('platform.help.kb_translation_pending_body') }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
            </form>
        </div>
    @endif
</div>
