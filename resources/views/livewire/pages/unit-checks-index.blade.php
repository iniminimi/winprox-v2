<div class="wp-stack" data-manual-capture="unit-checks">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="tasks"
                :title="__('unit_checks.title')"
                help-page="unit-checks"
                :subtitle="__('unit_checks.subtitle')"
            />
        </div>
        <div class="wp-cluster">
            @can('create', App\Models\UnitCheckList::class)
                <button type="button" class="btn btn--primary btn--sm" wire:click="openCreateList">
                    {{ __('unit_checks.lists.create') }}
                </button>
            @endcan
        </div>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <p class="wp-section-title">{{ __('unit_checks.lists.title') }}</p>
        <p class="wp-muted wp-text-sm">{{ __('unit_checks.lists.lead') }}</p>

        <div class="wp-list">
            @forelse ($lists as $list)
                <div class="wp-card wp-card-pad wp-cluster wp-cluster--spread" wire:key="unit-check-list-{{ $list->id }}">
                    <div class="wp-stack-tight">
                        <div class="wp-cluster wp-cluster--wrap">
                            <strong>{{ $list->name }}</strong>
                            @if (! $list->is_active)
                                <span class="wp-pill wp-pill--closed">{{ __('unit_checks.lists.inactive') }}</span>
                            @endif
                        </div>
                        <p class="wp-muted wp-text-sm">
                            {{ trans_choice('unit_checks.lists.item_count', $list->items_count, ['count' => $list->items_count]) }}
                        </p>
                    </div>
                    <div class="wp-cluster">
                        @can('update', $list)
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditList({{ $list->id }})">
                                {{ __('common.button.edit') }}
                            </button>
                        @endcan
                        @can('delete', $list)
                            @if ($list->is_active)
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="deactivateList({{ $list->id }})">
                                    {{ __('unit_checks.lists.deactivate') }}
                                </button>
                            @endif
                        @endcan
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('unit_checks.lists.empty') }}</p>
            @endforelse
        </div>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-cluster wp-cluster--between wp-cluster--wrap">
            <p class="wp-section-title">{{ __('unit_checks.list_title') }}</p>
            <div class="wp-cluster wp-cluster--wrap">
                <select class="wp-select wp-select--compact" wire:model.live="resultFilter" aria-label="{{ __('unit_checks.filters.result') }}">
                    <option value="all">{{ __('unit_checks.filters.all_results') }}</option>
                    <option value="ok">{{ __('unit_checks.result.ok') }}</option>
                    <option value="not_ok">{{ __('unit_checks.result.not_ok') }}</option>
                </select>
                <select class="wp-select wp-select--compact" wire:model.live="locationFilter" aria-label="{{ __('unit_checks.filters.location') }}">
                    <option value="">{{ __('unit_checks.filters.all_locations') }}</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name ?: $location->address }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="wp-list">
            @forelse ($checks as $check)
                <div class="wp-card wp-card-pad wp-cluster wp-cluster--spread" wire:key="unit-check-{{ $check->id }}">
                    <div class="wp-stack-tight">
                        <div class="wp-cluster wp-cluster--wrap">
                            <strong>{{ $check->checked_at?->format('d-m-Y H:i') }}</strong>
                            <span class="wp-pill wp-pill--{{ $check->result->pillVariant() }}">
                                {{ __('unit_checks.result.'.$check->result->value) }}
                            </span>
                        </div>
                        <p class="wp-text-body">
                            {{ $check->location?->name }}
                            ·
                            {{ $check->unit?->name }}
                        </p>
                        <p class="wp-muted wp-text-sm">
                            {{ $check->worker?->displayName() ?? __('unit_checks.worker_unknown') }}
                            @if ($check->team)
                                · {{ $check->team->localizedName() }}
                            @endif
                            @if (is_array($check->checklist_items) && $check->checklist_items !== [])
                                · {{ implode(', ', $check->checklist_items) }}
                            @endif
                            @if ($check->hasGps())
                                ·
                                <a href="{{ $check->googleMapsUrl() }}" target="_blank" rel="noopener noreferrer" class="wp-link">
                                    {{ __('unit_checks.gps_link') }}
                                </a>
                            @endif
                        </p>
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('unit_checks.empty') }}</p>
            @endforelse
        </div>

        @if ($checks->hasPages())
            <div class="wp-pagination">
                {{ $checks->links() }}
            </div>
        @endif
    </div>

    @if ($showListModal)
        <x-wp-modal closeMethod="closeListModal">
            <form wire:submit="saveList" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">
                        {{ $editingListId ? __('unit_checks.lists.edit_title') : __('unit_checks.lists.create_title') }}
                    </h2>
                    <x-wp-modal-close wire:click="closeListModal" />
                </div>

                <label class="wp-field">
                    <span class="wp-label">{{ __('unit_checks.lists.fields.name') }}</span>
                    <input type="text" class="wp-input" wire:model="listName" />
                    @error('listName') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <label class="wp-field">
                    <span class="wp-label">{{ __('unit_checks.lists.fields.items') }}</span>
                    <textarea class="wp-textarea" rows="6" wire:model="listItemsText" placeholder="{{ __('unit_checks.lists.fields.items_ph') }}"></textarea>
                    <span class="wp-muted wp-text-sm">{{ __('unit_checks.lists.fields.items_hint') }}</span>
                    @error('listItemsText') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <label class="wp-field wp-cluster">
                    <input type="checkbox" wire:model="listIsActive" />
                    <span>{{ __('unit_checks.lists.fields.active') }}</span>
                </label>

                <div class="wp-cluster wp-cluster--end">
                    <button type="button" class="btn btn--ghost" wire:click="closeListModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif
</div>
