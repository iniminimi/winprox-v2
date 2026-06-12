<div class="wp-manual-section-statuses">
    <h3 class="wp-manual-section-statuses__title">{{ $block['title'] }}</h3>
    <p class="wp-manual-section-statuses__intro">{{ $block['intro'] }}</p>

    <div class="wp-manual-status-list">
        @foreach ($block['statuses'] as $status)
            <div class="wp-manual-status-list__row">
                <span class="wp-pill wp-pill--{{ $status['pill'] }}">{{ $status['label'] }}</span>
                <span class="wp-manual-status-list__text">{{ $status['text'] }}</span>
            </div>
        @endforeach
    </div>
</div>
