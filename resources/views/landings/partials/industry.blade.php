@php
    $key = $key ?? 'landings.industry';
    $visuals = $visuals ?? [];
    $blockClass = filled($visuals) ? 'wp-stack' : 'wp-card wp-card-pad wp-stack';
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
    ])
</div>

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
    ])
</div>

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
        'modifier' => 'wp-landing-visual--roles',
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

<div @class(['wp-welcome-split' => filled($visuals['why'] ?? null)])>
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
    ])
</div>

<article class="wp-card wp-card-pad wp-stack">
    <h2 class="wp-welcome-h3">{{ __("{$key}.start.title") }}</h2>
    <p class="wp-text-body">{{ __("{$key}.start.lead") }}</p>
    <p class="wp-text-body">{{ __("{$key}.start.trial") }}</p>
</article>

<div @class(['wp-welcome-split wp-landing-split--flip' => filled($visuals['close'] ?? null)])>
    <article @class([
        'wp-card wp-card-pad wp-stack',
        'wp-welcome-section--center' => empty($visuals['close'] ?? null),
    ])>
        <h2 class="wp-welcome-h3">{{ __("{$key}.close.title") }}</h2>
        <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __("{$key}.close.lead") }}</p>
        <p class="wp-text-body">{{ __("{$key}.flow") }}</p>
    </article>
    @include('landings.partials.visual', [
        'src' => $visuals['close'] ?? null,
        'alt' => filled($visuals['close'] ?? null) ? __("{$key}.visuals.close") : '',
    ])
</div>
