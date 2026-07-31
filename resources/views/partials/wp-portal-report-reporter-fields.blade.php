@php
    $requireContact = (bool) ($requireContact ?? false);
@endphp
<div class="wp-field">
    <label class="wp-label" for="reporter_first_name">{{ __('portal.worker.first_name') }}</label>
    <input id="reporter_first_name" type="text" class="wp-input" wire:model="reporter_first_name"
           placeholder="{{ $requireContact ? __('portal.report.reporter_first_name_ph_required') : __('portal.report.reporter_first_name_ph') }}"
           autocomplete="given-name"
           @if ($requireContact) required @endif>
    @error('reporter_first_name') <p class="wp-error">{{ $message }}</p> @enderror
</div>
<div class="wp-field">
    <label class="wp-label" for="reporter_last_name">{{ __('portal.worker.last_name') }}</label>
    <input id="reporter_last_name" type="text" class="wp-input" wire:model="reporter_last_name"
           placeholder="{{ $requireContact ? __('portal.report.reporter_last_name_ph_required') : __('portal.report.reporter_last_name_ph') }}"
           autocomplete="family-name"
           @if ($requireContact) required @endif>
    @error('reporter_last_name') <p class="wp-error">{{ $message }}</p> @enderror
</div>
<div class="wp-field">
    <label class="wp-label" for="reporter_email">{{ __('portal.report.reporter_email') }}</label>
    <input id="reporter_email" type="email" class="wp-input" wire:model="reporter_email"
           placeholder="{{ $requireContact ? __('portal.report.reporter_email_ph_required') : __('portal.report.reporter_email_ph') }}"
           autocomplete="email" inputmode="email"
           @if ($requireContact) required @endif>
    @error('reporter_email') <p class="wp-error">{{ $message }}</p> @enderror
</div>
