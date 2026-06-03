{{-- Taal + stijl: alleen op smalle schermen, vast bovenaan (niet op desktop). --}}
<div class="wp-mobile-prefs">
    <div class="wp-mobile-prefs-inner">
        @include('partials.wp-lang-switch', ['variant' => 'mobile'])
        @auth
            @include('partials.wp-theme-switch', ['variant' => 'mobile'])
        @endauth
    </div>
</div>
