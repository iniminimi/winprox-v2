<div class="wp-stack" data-manual-capture="checklists">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="tasks"
                :title="__('unit_checks.lists.title')"
                help-page="checklists"
                :subtitle="__('unit_checks.lists.lead')"
            />
        </div>
        <div class="wp-cluster wp-page-actions">
            @can('create', App\Models\UnitCheckList::class)
                <button type="button" class="btn btn--primary btn--sm" wire:click="openCreateCheckList">
                    {{ __('unit_checks.lists.create') }}
                </button>
            @endcan
        </div>
    </div>

    @if (session('success'))
        <div class="wp-flash wp-flash--success">{{ session('success') }}</div>
    @endif

    @error('checkListName')
        <div class="wp-flash wp-flash--warning">{{ $message }}</div>
    @enderror

    @can('create', App\Models\UnitCheckList::class)
        @if ($checkListStarters !== [])
            <div class="wp-cluster">
                <span class="wp-muted wp-text-sm">{{ __('unit_checks.lists.starters_label') }}</span>
                @foreach ($checkListStarters as $starter)
                    <button
                        type="button"
                        class="btn btn--ghost btn--sm"
                        wire:click="copyCheckListFromStarter('{{ $starter['key'] }}')"
                    >
                        {{ __($starter['name']) }}
                    </button>
                @endforeach
            </div>
        @endif
    @endcan

    @if ($checkLists->isNotEmpty())
        <div class="wp-list wp-list--entity-rows">
            @foreach ($checkLists as $list)
                <div class="wp-issue-row" wire:key="unit-check-list-{{ $list->id }}">
                    <div class="wp-grow wp-stack-tight">
                        <div class="wp-cluster">
                            <p class="wp-issue-card-title">{{ $list->localizedName() }}</p>
                            @if (! $list->is_active)
                                <span class="wp-pill wp-pill--closed">{{ __('unit_checks.lists.inactive') }}</span>
                            @endif
                        </div>
                        <p class="wp-muted wp-text-sm">
                            {{ $list->internalTeam?->localizedName() ?? __('unit_checks.lists.team_shared') }}
                            ·
                            {{ trans_choice('unit_checks.lists.item_count', $list->items_count, ['count' => $list->items_count]) }}
                        </p>
                    </div>
                    <div class="wp-cluster">
                        @can('update', $list)
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditCheckList({{ $list->id }})">
                                {{ __('common.button.edit') }}
                            </button>
                        @endcan
                        @can('delete', $list)
                            @if ($list->is_active)
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="deactivateCheckList({{ $list->id }})">
                                    {{ __('unit_checks.lists.deactivate') }}
                                </button>
                            @endif
                            @if ($list->units_count === 0)
                                <button
                                    type="button"
                                    class="btn btn--ghost btn--sm"
                                    wire:click="deleteCheckList({{ $list->id }})"
                                    wire:confirm="{{ __('unit_checks.lists.confirm_delete') }}"
                                >
                                    {{ __('common.button.delete') }}
                                </button>
                            @endif
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="wp-card wp-card-pad">
            <p class="wp-muted">{{ __('unit_checks.lists.empty') }}</p>
        </div>
    @endif

    @if ($showCheckListModal)
        <x-wp-modal closeMethod="closeCheckListModal">
            <form wire:submit="saveCheckList" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">
                        {{ $editingCheckListId ? __('unit_checks.lists.edit_title') : __('unit_checks.lists.create_title') }}
                    </h2>
                    <x-wp-modal-close wire:click="closeCheckListModal" />
                </div>

                <div class="wp-field">
                    <label class="wp-label" for="checkListName">{{ __('unit_checks.lists.fields.name') }}</label>
                    <input type="text" id="checkListName" class="wp-input" wire:model="checkListName" maxlength="255">
                    @error('checkListName') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                @if ($editingCheckListId !== null)
                    <div class="wp-field" x-data="{ open: false }">
                        <span class="wp-label">{{ __('unit_checks.lists.translation_edit.label') }}</span>

                        <div class="wp-field-panel" :class="{ 'is-open': open }">
                            <button
                                type="button"
                                class="wp-field-panel__trigger"
                                @click="open = !open"
                                :aria-expanded="open"
                            >
                                <span>{{ __('unit_checks.lists.translation_edit.open') }}</span>
                                <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': open }" />
                            </button>

                            <div class="wp-field-panel__body wp-stack-tight" x-show="open" x-cloak>
                                <div class="wp-cluster wp-issue-description-row">
                                    <select
                                        class="wp-select wp-select--compact"
                                        wire:model.live="checkListPreviewLocale"
                                        aria-label="{{ __('issues.show.description_language') }}"
                                    >
                                        @foreach ($checkListTranslationLocales as $code => $label)
                                            <option value="{{ $code }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <label class="wp-field">
                                    <span class="wp-label">{{ __('unit_checks.lists.translation_edit.name') }}</span>
                                    <textarea class="wp-input" wire:model="checkListTranslationName" rows="1"></textarea>
                                    @error('checkListTranslationName') <span class="wp-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="wp-field">
                                    <span class="wp-label">{{ __('unit_checks.lists.translation_edit.items') }}</span>
                                    <textarea
                                        class="wp-input"
                                        rows="6"
                                        wire:model="checkListTranslationItemsText"
                                        placeholder="{{ __('unit_checks.lists.fields.items_ph') }}"
                                    ></textarea>
                                    <p class="wp-hint">{{ __('unit_checks.lists.translation_edit.items_hint') }}</p>
                                    @error('checkListTranslationItemsText') <span class="wp-error">{{ $message }}</span> @enderror
                                </label>

                                <div class="wp-row">
                                    <button
                                        type="button"
                                        class="btn btn--ghost btn--sm"
                                        wire:click="saveCheckListTranslationOverride"
                                        wire:loading.attr="disabled"
                                        wire:target="saveCheckListTranslationOverride"
                                    >
                                        <span wire:loading wire:target="saveCheckListTranslationOverride" class="wp-mr-2">
                                            <x-wp-spinner size="sm" />
                                        </span>
                                        <span>{{ __('unit_checks.lists.translation_edit.save') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="wp-field">
                    <label class="wp-label" for="checkListTeamId">{{ __('unit_checks.lists.fields.team') }}</label>
                    <select id="checkListTeamId" class="wp-input" wire:model="checkListTeamId">
                        <option value="">{{ __('unit_checks.lists.fields.team_shared_option') }}</option>
                        @foreach ($checkListTeams as $teamOption)
                            <option value="{{ $teamOption->id }}">{{ $teamOption->localizedName() }}</option>
                        @endforeach
                    </select>
                    <p class="wp-hint">{{ __('unit_checks.lists.fields.team_hint') }}</p>
                    @error('checkListTeamId') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-field">
                    <label class="wp-label" for="checkListItemsText">{{ __('unit_checks.lists.fields.items') }}</label>
                    <textarea
                        id="checkListItemsText"
                        class="wp-input"
                        rows="6"
                        wire:model="checkListItemsText"
                        placeholder="{{ __('unit_checks.lists.fields.items_ph') }}"
                    ></textarea>
                    <p class="wp-hint">{{ __('unit_checks.lists.fields.items_hint') }}</p>
                    @error('checkListItemsText') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <label class="wp-check">
                    <input type="checkbox" wire:model="checkListIsActive">
                    {{ __('unit_checks.lists.fields.active') }}
                </label>

                <div class="wp-cluster wp-cluster--tight">
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                    <button type="button" class="btn btn--ghost" wire:click="closeCheckListModal">{{ __('common.button.cancel') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif
</div>
