@if ($showManualClockInModal)
    <x-wp-modal closeMethod="closeManualClockIn" aria-labelledby="manual-clock-in-title">
        <form wire:submit="confirmManualClockIn" class="wp-card wp-modal-card wp-modal-card--form">
            <div class="wp-modal-head wp-modal-head--bordered">
                <div class="wp-stack-tight">
                    <h2 id="manual-clock-in-title" class="wp-section-title">{{ __('time.manual_clock_in.title') }}</h2>
                    <p class="wp-muted wp-text-sm">{{ __('time.manual_clock_in.subtitle') }}</p>
                </div>
                <x-wp-modal-close wire:click="closeManualClockIn" />
            </div>
            <div class="wp-modal-body wp-stack">
                <div class="wp-form-grid-2">
                    <div class="wp-field">
                        <label class="wp-label" for="manual-clock-in-worker">{{ __('time.manual_clock_in.fields.worker') }}</label>
                        <select id="manual-clock-in-worker" class="wp-select" wire:model="manualClockInWorkerId">
                            <option value="">{{ __('time.manual_clock_in.fields.worker_placeholder') }}</option>
                            @foreach ($manualClockInWorkers as $worker)
                                <option value="{{ $worker->id }}">{{ $worker->displayName() }}</option>
                            @endforeach
                        </select>
                        @error('manualClockInWorkerId') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="manual-clock-in-clock-point">{{ __('time.manual_clock_in.fields.clock_point') }}</label>
                        <select id="manual-clock-in-clock-point" class="wp-select" wire:model="manualClockInClockPointId">
                            <option value="">{{ __('time.manual_clock_in.fields.clock_point_placeholder') }}</option>
                            @foreach ($manualClockInClockPoints as $clockPoint)
                                <option value="{{ $clockPoint->id }}">{{ $clockPoint->name }}</option>
                            @endforeach
                        </select>
                        @error('manualClockInClockPointId') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="manual-clock-in-reason">{{ __('time.manual_clock_in.fields.reason') }}</label>
                    <textarea id="manual-clock-in-reason" class="wp-input" rows="3" wire:model="manualClockInReason"></textarea>
                    @error('manualClockInReason') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="wp-modal-foot">
                <button type="button" class="btn btn--ghost" wire:click="closeManualClockIn">{{ __('common.button.cancel') }}</button>
                <button type="submit" class="btn btn--primary">{{ __('time.manual_clock_in.submit') }}</button>
            </div>
        </form>
    </x-wp-modal>
@endif
