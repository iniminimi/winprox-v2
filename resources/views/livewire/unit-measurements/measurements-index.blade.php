<div class="wp-stack" data-manual-capture="unit-measurements-index">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="tasks"
                :title="__('unit_measurements.list.title')"
                help-page="unit-measurements.index"
                :subtitle="__('unit_measurements.list.subtitle')"
            />
        </div>
    </div>

    @if (session('success'))
        <div class="wp-flash wp-flash--success">{{ session('success') }}</div>
    @endif

    <x-wp-disclosure-card
        entangle="fieldsOpen"
        :title="__('unit_measurements.fields.section_title')"
        :subtitle="__('unit_measurements.fields.section_subtitle')"
        :count="$measureFields->count()"
    >
        <x-slot:toolbar>
            @can('create', \App\Models\UnitMeasureField::class)
                <button type="button" class="btn btn--primary btn--sm" wire:click="openCreateModal">
                    {{ __('unit_measurements.fields.add') }}
                </button>
            @endcan
        </x-slot:toolbar>

        <div class="wp-stack">
            @forelse ($measureFields as $field)
                <div class="wp-data-row" wire:key="measure-field-{{ $field->id }}">
                    <div class="wp-data-row-main">
                        <span class="wp-data-row-title">{{ $field->name }}</span>
                        <p class="wp-muted wp-text-sm">
                            {{ __('unit_measurements.types.'.$field->type->value) }}
                            @if ($field->unit_of_measure)
                                · {{ $field->unit_of_measure }}
                            @endif
                            ·
                            <span class="wp-pill {{ $field->is_active ? 'wp-pill--done' : 'wp-pill--closed' }}">
                                {{ $field->is_active ? __('unit_measurements.status.active') : __('unit_measurements.status.inactive') }}
                            </span>
                        </p>
                    </div>
                    <div class="wp-cluster wp-cluster--tight">
                        @can('update', $field)
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditModal({{ $field->id }})">
                                {{ __('common.button.edit') }}
                            </button>
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleActive({{ $field->id }})">
                                {{ $field->is_active ? __('unit_measurements.fields.deactivate') : __('unit_measurements.fields.activate') }}
                            </button>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('unit_measurements.fields.empty') }}</p>
            @endforelse
        </div>
    </x-wp-disclosure-card>

    @if ($total > 0 || $hasFilters || $measureFields->isNotEmpty())
        <div class="wp-card wp-filter-panel">
            <div class="wp-filter-form">
                <p class="wp-filter-form__title">{{ __('common.list.filters_title') }}</p>

                <div class="wp-filter-form__row">
                    <div class="wp-filter-cell">
                        <label class="wp-filter-inline-label" for="fieldFilter">{{ __('unit_measurements.filter.field') }}</label>
                        <select id="fieldFilter" class="wp-select" wire:model.defer="fieldFilter">
                            <option value="">{{ __('unit_measurements.filter.all_fields') }}</option>
                            @foreach ($filterFields as $field)
                                <option value="{{ $field->id }}">{{ $field->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wp-filter-cell">
                        <label class="wp-filter-inline-label" for="locationFilter">{{ __('unit_measurements.filter.location') }}</label>
                        <select id="locationFilter" class="wp-select" wire:model.defer="locationFilter">
                            <option value="">{{ __('unit_measurements.filter.all_locations') }}</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name ?: $location->address }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="wp-filter-form__row wp-filter-form__row--search">
                    <div class="wp-filter-cell wp-filter-cell--search">
                        <label class="wp-filter-inline-label" for="search">{{ __('unit_measurements.filter.search') }}</label>
                        <input type="search" id="search" class="wp-input" wire:model.defer="search"
                               placeholder="{{ __('unit_measurements.filter.search_placeholder') }}">
                    </div>
                </div>

                <div class="wp-filter-form__actions">
                    <button type="button" class="btn btn--primary btn--sm" wire:click="applyFilters">{{ __('unit_measurements.filter.apply') }}</button>
                    <x-wp-list-export :csv-url="$exportUrl" :print-url="$printUrl" />
                    @if ($hasFilters)
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="resetFilters">{{ __('unit_measurements.filter.reset') }}</button>
                    @endif
                </div>
            </div>
            <p class="wp-hint wp-filter-panel-hint">{{ __('unit_measurements.filter.hint') }}</p>
        </div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <p class="wp-section-title">{{ __('unit_measurements.list.results_title') }}</p>

        <div class="wp-list">
            @forelse ($measurements as $measurement)
                <div class="wp-card wp-card-pad wp-cluster wp-cluster--spread" wire:key="unit-measurement-{{ $measurement->id }}">
                    <div class="wp-stack-tight">
                        <div class="wp-cluster wp-cluster--wrap">
                            <strong>{{ $measurement->recorded_at?->format('d-m-Y H:i') }}</strong>
                            <span class="wp-pill wp-pill--progress">{{ $measurement->field?->name }}</span>
                        </div>
                        <p class="wp-text-body">
                            {{ $measurement->displayValue() }}
                            · {{ $measurement->location?->name }}
                            · {{ $measurement->unit?->name }}
                        </p>
                        <p class="wp-muted wp-text-sm">
                            {{ $measurement->worker?->displayName() ?? __('unit_measurements.history.reporter_unknown') }}
                            · {{ __('unit_measurements.sources.'.$measurement->source->value) }}
                        </p>
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('unit_measurements.history.empty') }}</p>
            @endforelse
        </div>

        @if ($measurements->hasPages())
            <div class="wp-pagination">
                {{ $measurements->links() }}
            </div>
        @endif
    </div>

    @if ($showModal)
        <x-wp-modal closeMethod="closeModal" aria-labelledby="unit-measure-field-modal-title">
            <form wire:submit="save" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="unit-measure-field-modal-title" class="wp-section-title">
                        {{ $editingFieldId ? __('unit_measurements.fields.edit_title') : __('unit_measurements.fields.create_title') }}
                    </h2>
                    <x-wp-modal-close wire:click="closeModal" />
                </div>

                <div class="wp-modal-body wp-stack">
                    @if (! $editingFieldId)
                        <div class="wp-stack-tight">
                            <p class="wp-label">{{ __('unit_measurements.fields.templates.label') }}</p>
                            <div class="wp-cluster wp-cluster--wrap">
                                @foreach ($fieldTemplates as $template)
                                    <button
                                        type="button"
                                        class="btn btn--ghost btn--sm"
                                        wire:click="applyFieldTemplate('{{ $template['key'] }}')"
                                    >
                                        {{ $template['label'] }}
                                    </button>
                                @endforeach
                            </div>
                            <p class="wp-muted wp-text-sm">{{ __('unit_measurements.fields.templates.hint') }}</p>
                        </div>
                    @endif

                    <div class="wp-field">
                        <label class="wp-label" for="measure-field-name">{{ __('unit_measurements.fields.form.name') }}</label>
                        <input id="measure-field-name" type="text" class="wp-input" wire:model="name" maxlength="120" autocomplete="off">
                        @error('name') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="wp-field">
                        <label class="wp-label" for="measure-field-type">{{ __('unit_measurements.fields.form.type') }}</label>
                        <select
                            id="measure-field-type"
                            class="wp-select"
                            wire:model.live="type"
                            @disabled($editingHasMeasurements)
                        >
                            @foreach ($types as $typeOption)
                                <option value="{{ $typeOption->value }}">{{ __('unit_measurements.types.'.$typeOption->value) }}</option>
                            @endforeach
                        </select>
                        @error('type') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    @if ($type === 'numeric')
                        <div class="wp-field">
                            <label class="wp-label" for="measure-field-uom">{{ __('unit_measurements.fields.form.unit_of_measure') }}</label>
                            <input id="measure-field-uom" type="text" class="wp-input" wire:model="unitOfMeasure" maxlength="32" placeholder="km, °C, L…" autocomplete="off">
                            @error('unitOfMeasure') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="wp-cluster wp-cluster--wrap">
                            <div class="wp-field wp-grow">
                                <label class="wp-label" for="measure-field-min">{{ __('unit_measurements.fields.form.min_value') }}</label>
                                <input id="measure-field-min" type="number" step="any" class="wp-input" wire:model="minValue">
                                @error('minValue') <p class="wp-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="wp-field wp-grow">
                                <label class="wp-label" for="measure-field-max">{{ __('unit_measurements.fields.form.max_value') }}</label>
                                <input id="measure-field-max" type="number" step="any" class="wp-input" wire:model="maxValue">
                                @error('maxValue') <p class="wp-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif

                    @if ($type === 'choice')
                        <div class="wp-stack-tight">
                            <p class="wp-label">{{ __('unit_measurements.fields.form.options') }}</p>
                            @foreach ($choiceOptions as $index => $option)
                                @php($optionLocked = filled($option) && in_array($option, $lockedChoiceOptions, true))
                                <div class="wp-cluster wp-cluster--tight" wire:key="choice-opt-{{ $index }}">
                                    <input
                                        type="text"
                                        class="wp-input wp-grow"
                                        wire:model="choiceOptions.{{ $index }}"
                                        maxlength="120"
                                        autocomplete="off"
                                        @if ($optionLocked) readonly @endif
                                    >
                                    @if (! $optionLocked)
                                        <button type="button" class="btn btn--ghost btn--sm" wire:click="removeChoiceOption({{ $index }})">
                                            {{ __('common.button.delete') }}
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                            @error('choiceOptions') <p class="wp-error">{{ $message }}</p> @enderror
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="addChoiceOption">
                                {{ __('unit_measurements.fields.form.add_option') }}
                            </button>
                        </div>
                    @endif
                </div>

                <div class="wp-modal-foot">
                    <div class="wp-cluster wp-cluster--end">
                        <button type="button" class="btn btn--ghost" wire:click="closeModal">{{ __('common.button.cancel') }}</button>
                        <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                    </div>
                </div>
            </form>
        </x-wp-modal>
    @endif
</div>
