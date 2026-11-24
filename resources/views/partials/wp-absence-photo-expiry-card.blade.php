<div class="wp-card wp-card-pad wp-stack-tight">
    <p class="wp-text-body"><strong>{{ __('dashboard.absence_photos.title') }}</strong></p>
    <p class="wp-muted">{{ __('dashboard.absence_photos.body') }}</p>
    <div class="wp-cluster wp-cluster--tight">
        <a href="{{ route('time.absence-requests.index', ['photos' => 'expiring']) }}" class="btn btn--ghost btn--sm">
            {{ __('dashboard.absence_photos.cta') }}
        </a>
    </div>
</div>
