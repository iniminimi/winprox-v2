@php
    $setupTitle = __($setupKey.'.title');
    $steps = __($setupKey.'.steps');
@endphp

@if (is_array($steps) && $steps !== [])
    <div class="wp-stack-tight wp-mt-tight">
        <p class="wp-section-title">{{ $setupTitle }}</p>
        <ol class="wp-list-plain wp-text-sm wp-muted wp-stack-tight">
            @foreach ($steps as $step)
                <li>{{ $step }}</li>
            @endforeach
        </ol>
    </div>
@endif
