<div class="wp-stack" data-manual-capture="esg-indicators">
    <x-wp-page-head-title
        icon="sliders"
        :title="__('esg.title')"
        help-page="esg.indicators"
        :subtitle="__('esg.subtitle')"
    />

    @include('partials.wp-esg-subnav')

    @if (session('success'))
        <div class="wp-card wp-card-pad">
            <p class="wp-text-body">{{ session('success') }}</p>
        </div>
    @endif

    <div class="wp-card wp-card-pad wp-stack-tight">
        <div class="wp-cluster wp-cluster--between">
            <p class="wp-section-title">{{ __('esg.list_title') }}</p>
            @can('create', App\Models\EsgIndicator::class)
                <button type="button" class="btn btn--primary btn--sm" wire:click="openCreateModal">
                    {{ __('esg.add') }}
                </button>
            @endcan
        </div>

        @if ($indicators->isEmpty())
            <p class="wp-muted">{{ __('esg.empty') }}</p>
            @include('partials.wp-esg-setup-steps', ['setupKey' => 'esg.setup'])
        @else
            <ul class="wp-list-plain wp-stack-tight">
                @foreach ($indicators as $indicator)
                    <li class="wp-list-row">
                        <div>
                            <strong>{{ $indicator->name }}</strong>
                            <p class="wp-muted wp-text-sm">
                                {{ __('esg.types.'.$indicator->type->value) }}
                                @if ($indicator->unit_of_measure)
                                    · {{ $indicator->unit_of_measure }}
                                @endif
                            </p>
                        </div>
                        <div class="wp-cluster">
                            <span class="wp-pill {{ $indicator->is_active ? 'wp-pill--done' : 'wp-pill--closed' }}">
                                {{ $indicator->is_active ? __('esg.status.active') : __('esg.status.inactive') }}
                            </span>
                            @can('update', $indicator)
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditModal({{ $indicator->id }})">
                                    {{ __('common.button.edit') }}
                                </button>
                            @endcan
                            @can('deactivate', $indicator)
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleActive({{ $indicator->id }})">
                                    {{ $indicator->is_active ? __('esg.actions.deactivate') : __('esg.actions.activate') }}
                                </button>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($showModal)
        <x-wp-modal closeMethod="closeModal">
            <div class="wp-modal__panel wp-card wp-card-pad wp-stack-tight">
                <h2 class="wp-section-title">
                    {{ $editingIndicatorId ? __('esg.edit_title') : __('esg.create_title') }}
                </h2>

                <label class="wp-label" for="esg-name">{{ __('esg.fields.name') }}</label>
                <input id="esg-name" type="text" class="wp-input" wire:model="name" autocomplete="off">

                <label class="wp-label" for="esg-type">{{ __('esg.fields.type') }}</label>
                <select id="esg-type" class="wp-input" wire:model.live="type">
                    @foreach ($types as $indicatorType)
                        <option value="{{ $indicatorType->value }}">{{ __('esg.types.'.$indicatorType->value) }}</option>
                    @endforeach
                </select>

                @if ($type === 'numeric')
                    <label class="wp-label" for="esg-uom">{{ __('esg.fields.unit_of_measure') }}</label>
                    <input id="esg-uom" type="text" class="wp-input" wire:model="unitOfMeasure" placeholder="kWh" autocomplete="off">

                    <div class="wp-stack-tight">
                        <label class="wp-label" for="esg-threshold-min">{{ __('esg.fields.threshold_min') }}</label>
                        <input id="esg-threshold-min" type="number" step="any" class="wp-input" wire:model="thresholdMin">
                        <label class="wp-label" for="esg-threshold-max">{{ __('esg.fields.threshold_max') }}</label>
                        <input id="esg-threshold-max" type="number" step="any" class="wp-input" wire:model="thresholdMax">
                    </div>
                @endif

                @if ($type === 'choice')
                    <div class="wp-stack-tight">
                        <p class="wp-label">{{ __('esg.fields.choice_options') }}</p>
                        @foreach ($choiceOptions as $index => $choiceOption)
                            <div class="wp-cluster wp-cluster--tight" wire:key="esg-choice-option-{{ $index }}">
                                <input type="text"
                                       class="wp-input wp-field--grow"
                                       wire:model="choiceOptions.{{ $index }}"
                                       placeholder="{{ __('esg.fields.choice_option_placeholder') }}"
                                       autocomplete="off">
                                @if (count($choiceOptions) > 2)
                                    <button type="button"
                                            class="btn btn--ghost btn--sm"
                                            wire:click="removeChoiceOption({{ $index }})">
                                        {{ __('esg.actions.remove_option') }}
                                    </button>
                                @endif
                            </div>
                        @endforeach
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="addChoiceOption">
                            {{ __('esg.actions.add_option') }}
                        </button>
                    </div>
                @endif

                @error('name') <p class="wp-form-error">{{ $message }}</p> @enderror
                @error('type') <p class="wp-form-error">{{ $message }}</p> @enderror
                @error('thresholdMax') <p class="wp-form-error">{{ $message }}</p> @enderror
                @error('choiceOptions') <p class="wp-form-error">{{ $message }}</p> @enderror

                <div class="wp-cluster wp-cluster--end">
                    <button type="button" class="btn btn--ghost" wire:click="closeModal">
                        {{ __('common.button.cancel') }}
                    </button>
                    <button type="button" class="btn btn--primary" wire:click="save">
                        {{ __('common.button.save') }}
                    </button>
                </div>
            </div>
        </x-wp-modal>
    @endif
</div>
