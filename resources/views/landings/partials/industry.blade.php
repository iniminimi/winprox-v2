@php
    $key = $key ?? 'landings.industry';
@endphp

<article class="wp-card wp-card-pad wp-stack">
    <h2 class="wp-welcome-h3">{{ __("{$key}.problem.title") }}</h2>
    <p class="wp-text-body">{{ __("{$key}.problem.lead") }}</p>
    <ul class="wp-welcome-checklist">
        @foreach (__("{$key}.problem.questions") as $question)
            <li>{{ $question }}</li>
        @endforeach
    </ul>
</article>

<article class="wp-card wp-card-pad wp-stack">
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

<article class="wp-card wp-card-pad wp-stack">
    <h2 class="wp-welcome-h3">{{ __("{$key}.places.title") }}</h2>
    <p class="wp-text-body">{{ __("{$key}.places.lead") }}</p>
    <ul class="wp-welcome-checklist">
        @foreach (__("{$key}.places.items") as $place)
            <li>{{ $place }}</li>
        @endforeach
    </ul>
    <p class="wp-text-body">{{ __("{$key}.places.close") }}</p>
</article>

<div class="wp-stack">
    <h2 class="wp-welcome-h3">{{ __("{$key}.roles.title") }}</h2>
    @foreach (__("{$key}.roles.items") as $role)
        <article class="wp-card wp-card-pad wp-stack">
            <h3 class="wp-welcome-h3">{{ $role['title'] }}</h3>
            <p class="wp-text-body">{{ $role['body'] }}</p>
        </article>
    @endforeach
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

<article class="wp-card wp-card-pad wp-stack">
    <h2 class="wp-welcome-h3">{{ __("{$key}.why.title") }}</h2>
    <ul class="wp-welcome-checklist">
        @foreach (__("{$key}.why.items") as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
</article>

<article class="wp-card wp-card-pad wp-stack">
    <h2 class="wp-welcome-h3">{{ __("{$key}.start.title") }}</h2>
    <p class="wp-text-body">{{ __("{$key}.start.lead") }}</p>
    <p class="wp-text-body">{{ __("{$key}.start.trial") }}</p>
</article>

<article class="wp-card wp-card-pad wp-stack wp-welcome-section--center">
    <h2 class="wp-welcome-h3">{{ __("{$key}.close.title") }}</h2>
    <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __("{$key}.close.lead") }}</p>
    <p class="wp-text-body">{{ __("{$key}.flow") }}</p>
</article>
