<div class="wp-stack" data-manual-capture="unit-measurements-fields">
    @include('partials.wp-unit-measurements-subnav')

    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="tasks"
                :title="__('unit_measurements.fields.title')"
                help-page="unit-measurements.fields"
                :subtitle="__('unit_measurements.fields.subtitle')"
            />
        </div>
        <button type="button" class="btn btn--primary" wire:click="openCreateModal">
            {{ __('unit_measurements.fields.add') }}
        </button>
    </div>

    @if (session('success'))
        <div class="wp-flash wp-flash--success">{{ session('success') }}</div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        @forelse ($fields as $field)
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
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditModal({{ $field->id }})">
                        {{ __('common.button.edit') }}
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleActive({{ $field->id }})">
                        {{ $field->is_active ? __('unit_measurements.fields.deactivate') : __('unit_measurements.fields.activate') }}
                    </button>
                </div>
            </div>
        @empty
            <p class="wp-muted">{{ __('unit_measurements.fields.empty') }}</p>
        @endforelse
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
