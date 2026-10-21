@php
    $privacyUrl = route('legal.privacy');
    $privacyLink = '<a href="'.e($privacyUrl).'">'.e(__('legal.documents.privacy')).'</a>';
@endphp

<div class="wp-welcome-section">
    <div class="wp-welcome-main wp-welcome-section-inner wp-stack">
        <header class="wp-welcome-section--center">
            <span class="wp-welcome-eyebrow">{{ __('demo.eyebrow') }}</span>
            <h1 class="wp-welcome-h2">{{ __('demo.title') }}</h1>
            <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('demo.lead') }}</p>
        </header>

        <div class="wp-card wp-card-pad wp-stack">
            @if ($submitted)
                <div class="wp-flash wp-flash--success" role="status">
                    <p><strong>{{ __('demo.success.title') }}</strong></p>
                    <p>{{ __('demo.success.text') }}</p>
                </div>
                <a href="{{ route('welcome') }}" class="btn btn--ghost btn--block">{{ __('welcome.back_home') }}</a>
            @else
                <p class="wp-text-body">{{ __('demo.intro') }}</p>
                <p class="wp-text-body">{{ __('demo.no_ict') }}</p>
                <p class="wp-muted">{{ __('demo.request_note') }}</p>

                <form wire:submit="submit" class="wp-stack" novalidate>
                    @error('form')
                        <div class="wp-flash wp-flash--danger" role="alert">{{ $message }}</div>
                    @enderror

                    <div>
                        <label for="demo-company" class="wp-label">{{ __('demo.form.company') }}</label>
                        <input type="text" id="demo-company" class="wp-input" wire:model="company"
                               autocomplete="organization" autofocus>
                        @error('company') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="demo-name" class="wp-label">{{ __('demo.form.name') }}</label>
                        <input type="text" id="demo-name" class="wp-input" wire:model="name"
                               autocomplete="name">
                        @error('name') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="demo-email" class="wp-label">{{ __('demo.form.email') }}</label>
                        <input type="email" id="demo-email" class="wp-input" wire:model="email"
                               autocomplete="email">
                        @error('email') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="demo-phone" class="wp-label">{{ __('demo.form.phone') }}</label>
                        <input type="tel" id="demo-phone" class="wp-input" wire:model="phone"
                               inputmode="tel" autocomplete="tel">
                        @error('phone') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="demo-municipality" class="wp-label">{{ __('demo.form.municipality') }}</label>
                        <input type="text" id="demo-municipality" class="wp-input" wire:model="municipality"
                               autocomplete="address-level2">
                        @error('municipality') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    <fieldset class="wp-stack-tight">
                        <legend class="wp-label">{{ __('demo.form.preference') }}</legend>
                        <label class="wp-check wp-check--boxed">
                            <input type="radio" wire:model="preference" value="onsite">
                            <span>
                                {{ __('demo.form.preference_onsite') }}<br>
                                <span class="wp-muted wp-text-sm">{{ __('demo.form.preference_onsite_hint') }}</span>
                            </span>
                        </label>
                        <label class="wp-check wp-check--boxed">
                            <input type="radio" wire:model="preference" value="video">
                            <span>
                                {{ __('demo.form.preference_video') }}<br>
                                <span class="wp-muted wp-text-sm">{{ __('demo.form.preference_video_hint') }}</span>
                            </span>
                        </label>
                        @error('preference') <p class="wp-error">{{ $message }}</p> @enderror
                    </fieldset>

                    <div>
                        <label for="demo-note" class="wp-label">{{ __('demo.form.note') }}</label>
                        <textarea id="demo-note" class="wp-textarea" wire:model="note"
                                  rows="3" placeholder="{{ __('demo.form.note_placeholder') }}"></textarea>
                        @error('note') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="btn btn--primary btn--block" wire:loading.attr="disabled" wire:target="submit">
                        <x-wp-spinner wire:loading wire:target="submit" class="wp-mr-2" />
                        <span wire:loading.remove wire:target="submit">{{ __('demo.form.submit') }}</span>
                        <span wire:loading wire:target="submit">{{ __('demo.form.submit_loading') }}</span>
                    </button>

                    <p class="wp-muted wp-text-sm">{!! __('demo.form.privacy_html', ['privacy' => $privacyLink]) !!}</p>
                </form>
            @endif
        </div>
    </div>
</div>
