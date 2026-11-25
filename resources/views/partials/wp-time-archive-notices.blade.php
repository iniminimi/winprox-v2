@if ($timeArchiveYear)
    <div class="wp-card wp-card-pad wp-stack-tight">
        <p class="wp-text-body"><strong>{{ __('dashboard.time_archives.year_title') }}</strong></p>
        <p class="wp-muted">{{ __('dashboard.time_archives.year_body', ['year' => $timeArchiveYear]) }}</p>
        @if ($canOpenSubscription)
            <div class="wp-cluster wp-cluster--tight">
                <a href="{{ route('subscription.index') }}" class="btn btn--ghost btn--sm">
                    {{ __('dashboard.time_archives.year_cta') }}
                </a>
            </div>
        @endif
    </div>
@endif

@if ($timeArchivePhotoUntil)
    <div class="wp-card wp-card-pad wp-stack-tight">
        <p class="wp-text-body"><strong>{{ __('dashboard.time_archives.photos_title') }}</strong></p>
        <p class="wp-muted">{{ __('dashboard.time_archives.photos_body', ['date' => $timeArchivePhotoUntil->timezone('Europe/Brussels')->subDay()->format('d-m-Y')]) }}</p>
        @if ($canOpenSubscription)
            <div class="wp-cluster wp-cluster--tight">
                <a href="{{ route('subscription.index') }}" class="btn btn--ghost btn--sm">
                    {{ __('dashboard.time_archives.photos_cta') }}
                </a>
            </div>
        @endif
    </div>
@endif
