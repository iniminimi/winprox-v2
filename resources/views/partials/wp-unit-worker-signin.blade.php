{{-- Worker-aanmelding op het unit-QR portaal (identificatie → icoonbevestiging). --}}
<div class="wp-card wp-card-pad wp-stack wp-signin">
    <h2 class="wp-section-title">{{ __('portal.worker.title') }}</h2>

    @if ($phase === \App\Support\Portal\UnitSignInPhase::PHASE_IDENTIFY)
        <p class="wp-muted">{{ __('portal.worker.identify_hint') }}</p>
        <form wire:submit="identifyWorker" class="wp-stack">
            <div class="wp-field">
                <label class="wp-label" for="first_name">{{ __('portal.worker.first_name') }}</label>
                <input id="first_name" type="text" class="wp-input" wire:model="first_name" autocomplete="given-name">
            </div>
            <div class="wp-field">
                <label class="wp-label" for="last_name">{{ __('portal.worker.last_name') }}</label>
                <input id="last_name" type="text" class="wp-input" wire:model="last_name" autocomplete="family-name">
            </div>
            @error('first_name') <p class="wp-error">{{ $message }}</p> @enderror
            @error('last_name') <p class="wp-error">{{ $message }}</p> @enderror
            @error('identify') <p class="wp-error">{{ $message }}</p> @enderror
            <button type="submit" class="btn btn--primary btn--block">{{ __('portal.worker.continue') }}</button>
        </form>

    @elseif ($phase === \App\Support\Portal\UnitSignInPhase::PHASE_VERIFY)
        <p class="wp-muted">
            {{ __('portal.worker.verify_hint') }}
            @if ($deviceWorker) <strong>{{ $deviceWorker->displayName() }}</strong>@endif
        </p>
        <div class="wp-icon-grid">
            @foreach (\App\Support\Portal\WorkerIcon::SLUGS as $slug)
                <button type="button"
                        wire:key="signin-icon-{{ $slug }}"
                        wire:click="$set('sign_in_icon_slug', '{{ $slug }}')"
                        @class(['wp-icon-tile', 'is-selected' => $sign_in_icon_slug === $slug])
                        title="{{ \App\Support\Portal\WorkerIcon::label($slug) }}"
                        aria-label="{{ \App\Support\Portal\WorkerIcon::label($slug) }}">
                    <x-wp-worker-icon :slug="$slug" />
                </button>
            @endforeach
        </div>
        @error('sign_in_icon_slug') <p class="wp-error">{{ $message }}</p> @enderror
        <p class="wp-hint">{{ __('portal.worker.attempts_left', ['count' => $remainingAttempts]) }}</p>
        <button type="button" class="btn btn--primary btn--block" wire:click="signInWithIcon" @disabled($sign_in_icon_slug === '')>
            {{ __('portal.worker.confirm_icon') }}
        </button>
        <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="signInAsDifferentWorker">
            {{ __('portal.worker.different_worker') }}
        </button>

    @elseif ($phase === \App\Support\Portal\UnitSignInPhase::PHASE_BLOCKED)
        <p class="wp-error">{{ __('portal.worker.errors.blocked') }}</p>
        <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="signInAsDifferentWorker">
            {{ __('portal.worker.different_worker') }}
        </button>

    @elseif ($phase === \App\Support\Portal\UnitSignInPhase::PHASE_NO_WORKERS)
        <p class="wp-muted">{{ __('portal.worker.no_workers') }}</p>

    @elseif ($phase === \App\Support\Portal\UnitSignInPhase::PHASE_WRONG_TEAM)
        <p class="wp-muted">{{ __('portal.worker.wrong_team') }}</p>
        <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="signInAsDifferentWorker">
            {{ __('portal.worker.different_worker') }}
        </button>
    @endif
</div>
