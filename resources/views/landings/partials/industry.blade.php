@php
    $key = $key ?? 'landings.industry';
    $visuals = $visuals ?? [];
    $visualModifiers = $visualModifiers ?? [];
    $visualLayouts = $visualLayouts ?? [];
    $closeStyle = $closeStyle ?? null;
    $blockClass = filled($visuals) ? 'wp-stack' : 'wp-card wp-card-pad wp-stack';

    $visualModifier = static function (string $slot) use ($visualModifiers): string {
        return $visualModifiers[$slot] ?? '';
    };
@endphp

<div @class(['wp-welcome-split' => filled($visuals['problem'] ?? null)])>
    <article class="{{ $blockClass }}">
        <h2 class="wp-welcome-h3">{{ __("{$key}.problem.title") }}</h2>
        <p class="wp-text-body">{{ __("{$key}.problem.lead") }}</p>
        <ul class="wp-welcome-checklist">
            @foreach (__("{$key}.problem.questions") as $question)
                <li>{{ $question }}</li>
            @endforeach
        </ul>
    </article>
    @include('landings.partials.visual', [
        'src' => $visuals['problem'] ?? null,
        'alt' => filled($visuals['problem'] ?? null) ? __("{$key}.visuals.problem") : '',
        'modifier' => $visualModifier('problem'),
    ])
</div>

<div @class(['wp-welcome-split wp-landing-split--flip' => filled($visuals['steps'] ?? null)])>
    <article class="{{ $blockClass }}">
        <h2 class="wp-welcome-h3">{{ __("{$key}.steps.title") }}</h2>
        <ul class="wp-welcome-checklist">
            @foreach (__("{$key}.steps.items") as $step)
                <li>
                    <strong>{{ $step['title'] }}</strong>
                    {{ $step['body'] }}
                </li>
            @endforeach
        </ul>
    </article>
    @include('landings.partials.visual', [
        'src' => $visuals['steps'] ?? null,
        'alt' => filled($visuals['steps'] ?? null) ? __("{$key}.visuals.steps") : '',
        'modifier' => $visualModifier('steps'),
    ])
</div>

@if (($visualLayouts['places'] ?? null) === 'wide')
    <div class="wp-landing-block wp-landing-block--wide-photo">
        <article class="{{ $blockClass }}">
            <h2 class="wp-welcome-h3">{{ __("{$key}.places.title") }}</h2>
            <p class="wp-text-body">{{ __("{$key}.places.lead") }}</p>
            <ul class="wp-welcome-checklist">
                @foreach (__("{$key}.places.items") as $place)
                    <li>{{ $place }}</li>
                @endforeach
            </ul>
            <p class="wp-text-body">{{ __("{$key}.places.close") }}</p>
        </article>
        @include('landings.partials.visual', [
            'src' => $visuals['places'] ?? null,
            'alt' => filled($visuals['places'] ?? null) ? __("{$key}.visuals.places") : '',
            'modifier' => $visualModifier('places'),
        ])
    </div>
@else
    <div @class(['wp-welcome-split' => filled($visuals['places'] ?? null)])>
        <article class="{{ $blockClass }}">
            <h2 class="wp-welcome-h3">{{ __("{$key}.places.title") }}</h2>
            <p class="wp-text-body">{{ __("{$key}.places.lead") }}</p>
            <ul class="wp-welcome-checklist">
                @foreach (__("{$key}.places.items") as $place)
                    <li>{{ $place }}</li>
                @endforeach
            </ul>
            <p class="wp-text-body">{{ __("{$key}.places.close") }}</p>
        </article>
        @include('landings.partials.visual', [
            'src' => $visuals['places'] ?? null,
            'alt' => filled($visuals['places'] ?? null) ? __("{$key}.visuals.places") : '',
            'modifier' => $visualModifier('places'),
        ])
    </div>
@endif

<div @class(['wp-welcome-split wp-landing-split--flip' => filled($visuals['roles'] ?? null)])>
    <div class="wp-stack">
        <h2 class="wp-welcome-h3">{{ __("{$key}.roles.title") }}</h2>
        <div class="wp-landing-roles">
            @foreach (__("{$key}.roles.items") as $role)
                <article class="wp-card wp-card-pad wp-stack">
                    <h3 class="wp-welcome-h3">{{ $role['title'] }}</h3>
                    <p class="wp-text-body">{{ $role['body'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
    @include('landings.partials.visual', [
        'src' => $visuals['roles'] ?? null,
        'alt' => filled($visuals['roles'] ?? null) ? __("{$key}.visuals.roles") : '',
        'modifier' => $visualModifier('roles'),
    ])
</div>

@if (\Illuminate\Support\Facades\Lang::has($key.'.sites.title'))
    <article class="wp-card wp-card-pad wp-stack">
        <h2 class="wp-welcome-h3">{{ __("{$key}.sites.title") }}</h2>
        <p class="wp-text-body">{{ __("{$key}.sites.lead") }}</p>
        @foreach (__("{$key}.sites.items") as $site)
            <div class="wp-stack-tight">
                <p class="wp-subhead">{{ $site['title'] }}</p>
                <p class="wp-text-body">{{ $site['body'] }}</p>
            </div>
        @endforeach
    </article>
@endif

<div @class(['wp-welcome-split wp-landing-split--feature' => filled($visuals['why'] ?? null)])>
    <article class="{{ $blockClass }}">
        <h2 class="wp-welcome-h3">{{ __("{$key}.why.title") }}</h2>
        <ul class="wp-welcome-checklist">
            @foreach (__("{$key}.why.items") as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </article>
    @include('landings.partials.visual', [
        'src' => $visuals['why'] ?? null,
        'alt' => filled($visuals['why'] ?? null) ? __("{$key}.visuals.why") : '',
        'modifier' => $visualModifier('why'),
    ])
</div>

<article class="wp-card wp-card-pad wp-stack">
    <h2 class="wp-welcome-h3">{{ __("{$key}.start.title") }}</h2>
    <p class="wp-text-body">{{ __("{$key}.start.lead") }}</p>
    <p class="wp-text-body">{{ __("{$key}.start.trial") }}</p>
</article>

@if (filled($visuals['close'] ?? null))
    <section @class([
        'wp-landing-close wp-landing-close--overlay',
        'wp-landing-close--scrim' => $closeStyle === 'scrim',
    ])>
        <img
            src="{{ asset($visuals['close']) }}"
            alt="{{ __("{$key}.visuals.close") }}"
            @class([
                'wp-landing-close__photo',
                $visualModifiers['close'] ?? null,
            ])
            loading="lazy"
            decoding="async"
        >
        <div class="wp-landing-close__copy">
            <h2 class="wp-welcome-h3">{{ __("{$key}.close.title") }}</h2>
            <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __("{$key}.close.lead") }}</p>
            <p class="wp-text-body">{{ __("{$key}.flow") }}</p>
        </div>
    </section>
@else
    <article class="wp-card wp-card-pad wp-stack wp-welcome-section--center">
        <h2 class="wp-welcome-h3">{{ __("{$key}.close.title") }}</h2>
        <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __("{$key}.close.lead") }}</p>
        <p class="wp-text-body">{{ __("{$key}.flow") }}</p>
    </article>
@endif
