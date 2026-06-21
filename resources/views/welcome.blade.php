@php
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" translate="no" data-theme="standard">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('welcome.meta_title') }}</title>
    @include('partials.social-meta', [
        'title' => __('welcome.social.og_title'),
        'description' => __('welcome.social.og_description'),
        'url' => route('welcome'),
    ])
    @include('partials.favicon')
    @vite(['resources/css/app.css'])
</head>
<body class="wp-shell wp-welcome-shell">
    <div class="wp-welcome-top">
    <nav class="wp-welcome-nav" aria-label="{{ __('welcome.meta_title') }}">
        <div class="wp-welcome-nav-inner">
            @include('partials.wp-welcome-brand')
            <div class="wp-welcome-nav-links">
                <a href="#hoe-het-werkt">{{ __('welcome.nav.how') }}</a>
                <a href="#melden">{{ __('welcome.nav.reporting') }}</a>
                <a href="#uitvoering">{{ __('welcome.nav.execution') }}</a>
                <a href="#organisaties">{{ __('welcome.nav.sectors') }}</a>
            </div>
            <div class="wp-welcome-nav-actions">
                @include('partials.wp-lang-switch', ['variant' => 'inline'])
                <a href="{{ route('login') }}" class="btn btn--ghost btn--sm">{{ __('welcome.login') }}</a>
                <a href="{{ route('register') }}" class="btn btn--primary btn--sm">{{ __('welcome.hero.cta_start') }}</a>
            </div>
        </div>
    </nav>

    <header class="wp-welcome-hero">
        <div class="wp-welcome-main">
            <span class="wp-welcome-badge">{{ __('welcome.hero.badge') }}</span>
            <h1 class="wp-welcome-h1">
                {{ __('welcome.hero.tagline_before') }}
                <em>{{ __('welcome.hero.tagline_highlight') }}</em>
            </h1>
            <p class="wp-welcome-lead">{{ __('welcome.hero.lead_intro') }}</p>
            <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.hero.lead_body') }}</p>
            <div class="wp-welcome-cta-row">
                <a href="{{ route('register') }}" class="btn btn--primary btn--lg">{{ __('welcome.hero.cta_start') }}</a>
            </div>
            <div class="wp-welcome-flow-grid" aria-label="{{ __('welcome.hero.badge') }}">
                @foreach (__('welcome.hero.flow_steps') as $index => $flowStep)
                    <div class="wp-welcome-flow-step wp-welcome-flow-step--{{ $index + 1 }}">
                        <span class="wp-welcome-flow-step-label">{{ $flowStep['step'] }}</span>
                        <span class="wp-welcome-flow-step-title">{{ $flowStep['title'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </header>
    </div>

    <main>
        <section id="talen" class="wp-welcome-section wp-welcome-section--center" aria-labelledby="welcome-languages-title">
            <div class="wp-welcome-section-inner">
                <div class="wp-welcome-languages-card">
                    <h2 id="welcome-languages-title" class="wp-welcome-h2">{{ __('welcome.languages.title') }}</h2>
                    <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.languages.body') }}</p>
                    <div class="wp-welcome-languages-glow" aria-hidden="true"></div>
                </div>
            </div>
        </section>

        <section id="video" class="wp-welcome-section wp-welcome-section--center" aria-labelledby="welcome-video-title">
            <div class="wp-welcome-section-inner">
                <span class="wp-welcome-eyebrow">{{ __('welcome.video.title') }}</span>
                <h2 id="welcome-video-title" class="wp-welcome-h2">{{ __('welcome.video.title') }}</h2>
                @php
                    $welcomeVideoRel = "video/{$locale}/issue_{$locale}_01.mp4";
                    $welcomeVideoAvailable = is_file(public_path($welcomeVideoRel));
                @endphp
                @if ($welcomeVideoAvailable)
                    @include('partials.wp-locale-video', [
                        'basename' => 'issue',
                        'title' => __('welcome.video.title'),
                    ])
                @else
                    <div class="wp-welcome-media-placeholder wp-welcome-media-placeholder--video" role="img" aria-label="{{ __('welcome.video.placeholder') }}">
                        <p>{{ __('welcome.video.placeholder') }}</p>
                    </div>
                @endif

                <div class="wp-welcome-video-widget">
                    <h3>{{ __('welcome.video.widget.title') }}</h3>
                    <p>{{ __('welcome.video.widget.body') }}</p>
                    <a href="{{ route('comparison') }}" class="btn btn--primary">{{ __('welcome.video.widget.link') }}</a>
                    <div class="wp-welcome-video-widget-glow" aria-hidden="true"></div>
                </div>
            </div>
        </section>

        <section id="hoe-het-werkt" class="wp-welcome-section" aria-labelledby="welcome-how-title">
            <div class="wp-welcome-main wp-welcome-section-inner--wide">
                <div class="wp-welcome-section--center wp-welcome-section-inner">
                    <span class="wp-welcome-eyebrow">{{ __('welcome.how.eyebrow') }}</span>
                    <h2 id="welcome-how-title" class="wp-welcome-h2">{{ __('welcome.how.title') }}</h2>
                    <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.how.lead') }}</p>
                </div>
                <div class="wp-welcome-split">
                    <div class="wp-welcome-pain-list">
                        @foreach (__('welcome.how.without') as $line)
                            <div class="wp-welcome-pain-item">
                                <span class="wp-welcome-pain-icon" aria-hidden="true"><x-wp-icon name="x-mark" class="wp-icon" /></span>
                                <span>{{ $line }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="wp-welcome-panel-dark">
                        <h3>{{ __('welcome.how.panel_title') }}</h3>
                        <ul class="wp-welcome-checklist wp-welcome-checklist--dark wp-welcome-checklist--accent">
                            @foreach (__('welcome.how.checklist') as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                        <div class="wp-welcome-panel-glow" aria-hidden="true"></div>
                    </div>
                </div>
            </div>
        </section>

        <section id="melden" class="wp-welcome-section wp-welcome-section--alt" aria-labelledby="welcome-reporting-title">
            <div class="wp-welcome-main wp-welcome-section-inner--wide">
                <div class="wp-welcome-split">
                    <div>
                        <span class="wp-welcome-eyebrow">{{ __('welcome.reporting.eyebrow') }}</span>
                        <h2 id="welcome-reporting-title" class="wp-welcome-h2">{{ __('welcome.reporting.title') }}</h2>
                        <div class="wp-welcome-pills">
                            @foreach (__('welcome.reporting.who') as $who)
                                <span class="wp-welcome-pill">{{ $who }}</span>
                            @endforeach
                        </div>
                        <p class="wp-welcome-lead wp-welcome-lead--sm">
                            {{ __('welcome.reporting.via_before') }}<strong>{{ __('welcome.reporting.via_qr') }}</strong>{{ __('welcome.reporting.via_after') }}
                        </p>
                        <p class="wp-welcome-badge-ok">{{ __('welcome.reporting.no_app') }}</p>
                    </div>
                    <div class="wp-welcome-steps-card">
                        @foreach (__('welcome.reporting.steps') as $index => $step)
                            <div class="wp-welcome-step-card">
                                <span class="wp-welcome-step-num">{{ $index + 1 }}</span>
                                <h4>{{ $step['title'] }}</h4>
                                <p>{{ $step['body'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section id="uitvoering" class="wp-welcome-section" aria-labelledby="welcome-execution-title">
            <div class="wp-welcome-main wp-welcome-section-inner--wide">
                <div class="wp-welcome-section--center wp-welcome-section-inner">
                    <span class="wp-welcome-eyebrow">{{ __('welcome.execution.eyebrow') }}</span>
                    <h2 id="welcome-execution-title" class="wp-welcome-h2">{{ __('welcome.execution.title') }}</h2>
                    <p class="wp-welcome-lead wp-welcome-lead--sm">
                        {{ __('welcome.execution.contrast_before') }}<em>{{ __('welcome.execution.contrast_highlight') }}</em>
                    </p>
                </div>
                <div class="wp-welcome-duo">
                    <div class="wp-welcome-card-soft">
                        <h3 class="wp-welcome-h3">{{ __('welcome.execution.team_portal_title') }}</h3>
                        <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.execution.team_portal_lead') }}</p>
                        <div class="wp-welcome-action-grid">
                            @foreach (__('welcome.execution.actions') as $action)
                                <div class="wp-welcome-action-chip">
                                    <x-wp-icon :name="$action['icon']" class="wp-icon" />
                                    <span>{{ $action['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="wp-welcome-auth-notes">
                            @foreach (__('welcome.execution.auth_notes') as $note)
                                <span>{{ $note }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="wp-welcome-panel-dark">
                        <span class="wp-welcome-eyebrow wp-welcome-eyebrow--accent">{{ __('welcome.briefing.eyebrow') }}</span>
                        <h3 class="wp-welcome-h3-lg">{{ __('welcome.briefing.title') }}</h3>
                        <p class="wp-welcome-muted">{{ __('welcome.briefing.intro') }}</p>
                        <div class="wp-welcome-briefing-list">
                            @foreach (__('welcome.briefing.mock') as $row)
                                <div @class(['wp-welcome-briefing-row', 'wp-welcome-briefing-row--priority' => ! empty($row['priority'])])>
                                    <span>{{ $row['label'] }}</span>
                                    <span @class(['wp-welcome-briefing-badge', 'wp-welcome-briefing-badge--priority' => ! empty($row['priority'])])>{{ $row['badge'] }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="wp-welcome-panel-glow" aria-hidden="true"></div>
                    </div>
                </div>

                <div class="wp-welcome-banner" aria-labelledby="welcome-qr-title">
                    <span class="wp-welcome-eyebrow wp-welcome-eyebrow--accent">{{ __('welcome.qr_history.concept') }}</span>
                    <h3 id="welcome-qr-title">{{ __('welcome.qr_history.title') }}</h3>
                    <p>{{ __('welcome.qr_history.body') }}</p>
                    <div class="wp-welcome-tag-row">
                        @foreach (__('welcome.qr_history.tags') as $tag)
                            <span class="wp-welcome-tag">{{ $tag }}</span>
                        @endforeach
                    </div>
                    <div class="wp-welcome-availability">
                        @foreach (__('welcome.qr_history.availability') as $item)
                            <span>✓ {{ $item }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section id="organisaties" class="wp-welcome-section wp-welcome-section--alt" aria-labelledby="welcome-sectors-title">
            <div class="wp-welcome-main wp-welcome-section-inner--wide">
                <div class="wp-welcome-section--center wp-welcome-section-inner">
                    <span class="wp-welcome-eyebrow">{{ __('welcome.sectors.eyebrow') }}</span>
                    <h2 id="welcome-sectors-title" class="wp-welcome-h2">{{ __('welcome.sectors.title') }}</h2>
                    <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.sectors.lead') }}</p>
                </div>
                <div class="wp-welcome-sector-grid">
                    @foreach (__('welcome.sectors.items') as $sector)
                        <article class="wp-welcome-sector-card">
                            <span class="wp-welcome-sector-icon"><x-wp-icon :name="$sector['icon']" class="wp-icon" /></span>
                            <h3>{{ $sector['name'] }}</h3>
                            <p>{{ $sector['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="wp-welcome-section wp-welcome-section--center" aria-labelledby="welcome-integrations-title">
            <div class="wp-welcome-section-inner">
                <span class="wp-welcome-eyebrow">{{ __('welcome.integrations.eyebrow') }}</span>
                <h2 id="welcome-integrations-title" class="wp-welcome-h2">{{ __('welcome.integrations.title') }}</h2>
                <p class="wp-welcome-lead wp-welcome-lead--sm">
                    {{ __('welcome.integrations.body_before') }}<strong>{{ __('welcome.integrations.body_highlight') }}</strong>{{ __('welcome.integrations.body_after') }}
                </p>
                <div class="wp-welcome-compare">
                    <div class="wp-welcome-compare-card">
                        <span class="wp-welcome-compare-kicker">{{ __('welcome.integrations.compare_other_label') }}</span>
                        {{ __('welcome.integrations.compare_other_text') }}
                    </div>
                    <div class="wp-welcome-compare-card wp-welcome-compare-card--winprox">
                        <span class="wp-welcome-compare-kicker">{{ __('welcome.integrations.compare_winprox_label') }}</span>
                        {{ __('welcome.integrations.compare_winprox_text') }}
                    </div>
                </div>
            </div>
        </section>

        <section class="wp-welcome-section wp-welcome-practical" aria-labelledby="welcome-practical-title">
            <div class="wp-welcome-main wp-welcome-section-inner--wide wp-welcome-practical-inner">
                <span class="wp-welcome-eyebrow wp-welcome-eyebrow--accent">{{ __('welcome.practical.eyebrow') }}</span>
                <h2 id="welcome-practical-title" class="wp-welcome-h2">{{ __('welcome.practical.title') }}</h2>
                <p class="wp-welcome-practical-sub">{{ __('welcome.practical.subtitle') }}</p>
                <div class="wp-welcome-practical-grid">
                    @foreach (__('welcome.practical.items') as $item)
                        <div class="wp-welcome-practical-item">{{ $item }}</div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="wp-welcome-section wp-welcome-section--alt wp-welcome-section--center" aria-labelledby="welcome-screenshots-title">
            <div class="wp-welcome-section-inner--wide wp-welcome-main">
                <span class="wp-welcome-eyebrow">{{ __('welcome.screenshots.title') }}</span>
                <h2 id="welcome-screenshots-title" class="wp-welcome-h2">{{ __('welcome.screenshots.title') }}</h2>
                <div class="wp-welcome-screenshots">
                    <div class="wp-welcome-media-placeholder" role="img" aria-label="{{ __('welcome.screenshots.desktop') }}">
                        <p>{{ __('welcome.screenshots.desktop') }}</p>
                    </div>
                    <div class="wp-welcome-media-placeholder wp-welcome-media-placeholder--phone" role="img" aria-label="{{ __('welcome.screenshots.mobile') }}">
                        <p>{{ __('welcome.screenshots.mobile') }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="wp-welcome-section wp-welcome-section--center" aria-labelledby="welcome-closing-title">
            <div class="wp-welcome-section-inner">
                <h2 id="welcome-closing-title" class="wp-welcome-h1 wp-welcome-h1--closing">
                    {{ __('welcome.closing.title_before') }}<br><em>{{ __('welcome.closing.title_highlight') }}</em>
                </h2>
                <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.closing.body') }}</p>
                <blockquote class="wp-welcome-quote">{{ __('welcome.closing.quote') }}</blockquote>
                <a href="{{ route('register') }}" class="btn btn--primary btn--lg">{{ __('welcome.closing.cta_start') }}</a>
            </div>
        </section>
    </main>

    <footer class="wp-welcome-footer">
        <div class="wp-welcome-footer-inner">
            @include('partials.wp-welcome-brand')
            <nav class="wp-welcome-footer-nav" aria-label="{{ __('legal.index_title') }}">
                @foreach (config('legal.documents', []) as $docMeta)
                    <a href="{{ route($docMeta['route']) }}" target="_blank" rel="noopener">{{ __($docMeta['label_key']) }}</a>
                @endforeach
                <a href="{{ route('contact.index') }}">{{ __('welcome.footer.contact') }}</a>
            </nav>
            <p>&copy; {{ date('Y') }} WinProx. {{ __('welcome.footer.rights') }}</p>
        </div>
    </footer>
</body>
</html>
