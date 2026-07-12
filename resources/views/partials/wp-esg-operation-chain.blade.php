@if ($steps !== [])
    <ol class="wp-esg-chain">
        @foreach ($steps as $step)
            <li class="wp-esg-chain__step" wire:key="esg-chain-{{ $step['key'] }}">
                <span class="wp-esg-chain__label">{{ $step['label'] }}</span>
                @if ($step['detail'])
                    <span class="wp-esg-chain__detail wp-muted wp-text-sm">{{ $step['detail'] }}</span>
                @endif
                @if ($step['status_label'])
                    <span class="wp-pill wp-pill--{{ $step['status_modifier'] ?? 'closed' }}">{{ $step['status_label'] }}</span>
                @endif
                @if ($step['url'])
                    <a href="{{ $step['url'] }}" class="btn btn--ghost btn--sm">{{ __('esg.chain.open') }}</a>
                @endif
            </li>
        @endforeach
    </ol>
@endif
