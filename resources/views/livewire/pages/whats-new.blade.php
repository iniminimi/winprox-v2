<div class="wp-stack">
    <x-wp-page-head-title
        :assistant-video="asset('video/assistant_new.mp4')"
        :title="__('whats_new.title')"
        help-page="whats_new"
        :subtitle="__('whats_new.subtitle')"
    />

    @forelse ($notes as $note)
        <article class="wp-card wp-card-pad wp-stack" wire:key="whats-new-{{ $note->date->toDateString() }}">
            <h2 class="wp-subhead">{{ $note->date->locale(app()->getLocale())->translatedFormat('j F Y') }}</h2>
            @foreach ($note->sections as $category => $items)
                <div class="wp-stack-tight">
                    <span @class([
                        'wp-pill',
                        'wp-pill--new' => $category === 'new',
                        'wp-pill--progress' => $category === 'improved',
                        'wp-pill--done' => $category === 'fixed',
                        'wp-pill--closed' => $category === 'important',
                    ])>{{ __('whats_new.categories.'.$category) }}</span>
                    @foreach ($items as $item)
                        <p class="wp-text-body">{{ $item }}</p>
                    @endforeach
                </div>
            @endforeach
        </article>
    @empty
        <p class="wp-muted">{{ __('whats_new.empty') }}</p>
    @endforelse
</div>
