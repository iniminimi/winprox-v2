<div class="wp-stack" data-manual-capture="time-shift-types">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                :title="__('time.shift_types.title')"
                help-page="time.shift_types"
                :subtitle="__('time.shift_types.subtitle')"
            />
        </div>
        @can('create', \App\Models\ShiftType::class)
            <button type="button" class="btn btn--primary" wire:click="openCreate">{{ __('time.schedule.types.add') }}</button>
        @endcan
    </div>

    @include('partials.wp-time-nav', ['alarmCount' => $alarmCount])

    @if (session('time_flash'))
        <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-list wp-list--entity-rows">
            @forelse ($shiftTypes as $type)
                <div class="wp-issue-row" wire:key="shift-type-{{ $type->id }}">
                    <div class="wp-grow wp-cluster wp-cluster--tight">
                        <span class="wp-roster-color-preview {{ $type->color->hasFill() ? 'wp-roster-cell--'.$type->color->value : '' }}" aria-hidden="true"></span>
                        <div class="wp-stack-tight">
                            <p class="wp-issue-card-title"><strong>{{ $type->code }}</strong> {{ $type->label }}</p>
                            <p class="wp-issue-card-meta">
                                {{ __('time.schedule.types.kinds.'.$type->kind->value) }}
                                @if ($type->kind->isWork())
                                    · {{ $type->start_time }}–{{ $type->end_time }} · {{ __('time.schedule.types.break') }} {{ $type->break_minutes }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="wp-cluster wp-cluster--wrap">
                        <span class="wp-pill {{ $type->is_active ? 'wp-pill--done' : 'wp-pill--closed' }}">
                            {{ $type->is_active ? __('time.shift_types.status.active') : __('time.shift_types.status.inactive') }}
                        </span>
                        @can('update', $type)
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="openEdit({{ $type->id }})">{{ __('common.button.edit') }}</button>
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleActive({{ $type->id }})">
                                {{ $type->is_active ? __('time.schedule.types.deactivate') : __('time.schedule.types.activate') }}
                            </button>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('time.shift_types.empty') }}</p>
            @endforelse
        </div>
    </div>

    @if ($showModal)
        <x-wp-modal closeMethod="closeModal">
            <form wire:submit="save" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-h2">{{ $editingTypeId ? __('time.schedule.types.edit') : __('time.schedule.types.add') }}</h2>
                    <x-wp-modal-close wire:click="closeModal" />
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="type-code">{{ __('time.schedule.types.code') }}</label>
                    <input id="type-code" class="wp-input" wire:model="typeCode" maxlength="8">
                    @error('typeCode') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="type-label">{{ __('time.schedule.types.label') }}</label>
                    <input id="type-label" class="wp-input" wire:model="typeLabel" maxlength="80">
                    @error('typeLabel') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="type-kind">{{ __('time.schedule.types.kind') }}</label>
                    <select id="type-kind" class="wp-select" wire:model.live="typeKind">
                        @foreach ($kinds as $kind)
                            <option value="{{ $kind->value }}">{{ __('time.schedule.types.kinds.'.$kind->value) }}</option>
                        @endforeach
                    </select>
                    @error('typeKind') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                @if ($typeKind === \App\Enums\ShiftTypeKind::Work->value)
                <div class="wp-measure-field-range">
                    <div class="wp-field">
                        <label class="wp-label" for="type-start">{{ __('time.schedule.types.start') }}</label>
                        <input id="type-start" class="wp-input" wire:model="typeStart" placeholder="07:00">
                        @error('typeStart') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="type-end">{{ __('time.schedule.types.end') }}</label>
                        <input id="type-end" class="wp-input" wire:model="typeEnd" placeholder="15:00">
                        @error('typeEnd') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="type-break">{{ __('time.schedule.types.break') }}</label>
                    <input id="type-break" type="number" min="0" max="720" class="wp-input" wire:model="typeBreak">
                    @error('typeBreak') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                @endif
                <div class="wp-field">
                    <label class="wp-label" for="type-color">{{ __('time.schedule.types.color') }}</label>
                    <div class="wp-cluster wp-cluster--tight">
                        <select id="type-color" class="wp-select" wire:model.live="typeColor">
                            @foreach ($colors as $color)
                                <option value="{{ $color->value }}">{{ __('time.schedule.types.colors.'.$color->value) }}</option>
                            @endforeach
                        </select>
                        <span class="wp-roster-color-preview {{ $typeColor !== 'none' ? 'wp-roster-cell--'.$typeColor : '' }}" aria-hidden="true"></span>
                    </div>
                </div>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--surface" wire:click="closeModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif
</div>
