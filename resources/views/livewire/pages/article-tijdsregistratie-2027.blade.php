@php
    $key = 'articles.tijdsregistratie_2027';
    $badgeClass = [
        'now' => 'wp-badge wp-badge-info',
        'announced' => 'wp-badge wp-badge-secondary',
        'pending' => 'wp-badge wp-badge-warning',
    ];
@endphp

<div class="wp-welcome-section wp-welcome-section--alt wp-welcome-faq-page">
    <div class="wp-welcome-main wp-welcome-section-inner--wide wp-stack">
        <div class="wp-welcome-section--center wp-welcome-section-inner">
            <span class="wp-welcome-eyebrow">{{ __("{$key}.eyebrow") }}</span>
            <h1 class="wp-welcome-h2">{{ __("{$key}.title") }}</h1>
            <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __("{$key}.lead") }}</p>
            <p class="wp-muted wp-text-sm">{{ __("{$key}.updated") }}</p>
        </div>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __("{$key}.summary.title") }}</h2>
            <ul class="wp-welcome-checklist">
                @foreach (__("{$key}.summary.items") as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __("{$key}.status.title") }}</h2>
            <p class="wp-text-body">{{ __("{$key}.status.intro") }}</p>
            <div class="wp-article-table-scroll">
                <table class="wp-article-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __("{$key}.status.col_topic") }}</th>
                            <th scope="col">{{ __("{$key}.status.col_state") }}</th>
                            <th scope="col">{{ __("{$key}.status.col_detail") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (__("{$key}.status.rows") as $row)
                            <tr>
                                <th scope="row">{{ $row['topic'] }}</th>
                                <td>
                                    <span class="{{ $badgeClass[$row['state']] ?? 'wp-badge' }}">
                                        {{ __("{$key}.status.state_{$row['state']}") }}
                                    </span>
                                </td>
                                <td>{{ $row['detail'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __("{$key}.changes.title") }}</h2>
            @foreach (__("{$key}.changes.paras") as $para)
                <p class="wp-text-body">{{ $para }}</p>
            @endforeach
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __("{$key}.who.title") }}</h2>
            <p class="wp-text-body">{{ __("{$key}.who.intro") }}</p>
            <ul class="wp-welcome-checklist">
                @foreach (__("{$key}.who.items") as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
            <p class="wp-muted wp-text-sm">{{ __("{$key}.who.note") }}</p>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __("{$key}.what.title") }}</h2>
            <p class="wp-text-body">{{ __("{$key}.what.intro") }}</p>
            <ul class="wp-welcome-checklist">
                @foreach (__("{$key}.what.items") as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __("{$key}.system.title") }}</h2>
            @foreach (__("{$key}.system.paras") as $para)
                <p class="wp-text-body">{{ $para }}</p>
            @endforeach
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __("{$key}.already.title") }}</h2>
            <p class="wp-text-body">{{ __("{$key}.already.intro") }}</p>
            <ul class="wp-welcome-checklist">
                @foreach (__("{$key}.already.items") as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
            <p class="wp-muted wp-text-sm">{{ __("{$key}.already.note") }}</p>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __("{$key}.sectors.title") }}</h2>
            <p class="wp-text-body">{{ __("{$key}.sectors.intro") }}</p>
            @foreach (__("{$key}.sectors.items") as $sector)
                <div class="wp-stack-tight">
                    <h3 class="wp-subhead">{{ $sector['name'] }}</h3>
                    <p class="wp-text-body">{{ $sector['body'] }}</p>
                </div>
            @endforeach
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __("{$key}.prepare.title") }}</h2>
            <p class="wp-text-body">{{ __("{$key}.prepare.intro") }}</p>
            <ul class="wp-welcome-checklist">
                @foreach (__("{$key}.prepare.items") as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __("{$key}.faq.title") }}</h2>
            @foreach (__("{$key}.faq.items") as $item)
                <div class="wp-stack-tight">
                    <h3 class="wp-subhead">{{ $item['q'] }}</h3>
                    <p class="wp-text-body">{{ $item['a'] }}</p>
                </div>
            @endforeach
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __("{$key}.sources.title") }}</h2>
            <p class="wp-text-body">{{ __("{$key}.sources.intro") }}</p>
            <ul class="wp-welcome-checklist">
                @foreach (__("{$key}.sources.items") as $source)
                    <li>
                        <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer">{{ $source['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </article>

        <article class="wp-card wp-card-pad wp-stack wp-welcome-section--center">
            <h2 class="wp-welcome-h3">{{ __("{$key}.winprox.title") }}</h2>
            <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __("{$key}.winprox.body") }}</p>
            <div class="wp-welcome-cta-row">
                <a href="{{ route('demo') }}" class="btn btn--primary btn--lg">{{ __("{$key}.winprox.cta_demo") }}</a>
                <a href="{{ route('contact.index') }}" class="btn btn--ghost btn--lg">{{ __("{$key}.winprox.cta_contact") }}</a>
                <a href="{{ route('pricing') }}" class="btn btn--ghost btn--lg">{{ __("{$key}.winprox.cta_pricing") }}</a>
            </div>
        </article>

        <p class="wp-muted wp-text-sm">{{ __("{$key}.disclaimer") }}</p>

        @include('partials.wp-marketing-related', [
            'links' => $relatedLinks,
            'title' => __("{$key}.related_title"),
        ])
    </div>
</div>
