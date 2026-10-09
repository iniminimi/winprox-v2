<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/**
 * Host-regels voor winprox.be / winprox.nl naast winprox.app.
 *
 * Drie standen, twee schakelaars per markt (config/marketing.php):
 * niet live → heel het domein (apex én www) 302 naar het app-domein;
 * live → marketing blijft hier, app-paden 302; permanent → die redirects en www→apex worden 301.
 */
final class MarketingDomain
{
    /** @var array<string, true>|null */
    private static ?array $suffixes = null;

    public static function appHost(): string
    {
        return self::normalizeHost((string) config('marketing.domains.app_host', 'winprox.app'));
    }

    public static function isLiveMarketRequest(?Request $request = null): bool
    {
        $request ??= request();
        $row = self::marketRow(self::normalizeHost($request->getHost()));

        return $row !== null && $row['live'] && ! $row['www'];
    }

    /**
     * Marktsleutel ('be', 'nl', …) van het huidige live marktdomein, of null
     * op het app-domein, een inactief domein, www of een onbekende host.
     */
    public static function marketKey(?Request $request = null): ?string
    {
        $request ??= request();
        $row = self::marketRow(self::normalizeHost($request->getHost()));
        if ($row === null || ! $row['live'] || $row['www']) {
            return null;
        }

        return $row['key'];
    }

    /**
     * Is deze route verborgen op de markt van het huidige request?
     */
    public static function routeHidden(string $routeName, ?Request $request = null): bool
    {
        $request ??= request();

        return self::routeHiddenOnHost($routeName, self::normalizeHost($request->getHost()));
    }

    /**
     * Is deze route verborgen op de markt van de gegeven host?
     * Wordt gebruikt door sitemap, hreflang en gerelateerde links.
     */
    public static function routeHiddenOnHost(string $routeName, string $host): bool
    {
        $row = self::marketRow(self::normalizeHost($host));
        if ($row === null || ! $row['live'] || $row['www'] || $row['hidden_paths'] === []) {
            return false;
        }

        $suffix = self::routeSuffix($routeName);
        if ($suffix === null) {
            return false;
        }

        return in_array($suffix, $row['hidden_paths'], true);
    }

    /**
     * Productdocs-secties (config/product_docs.php kaarten met 'key') die op
     * deze markt niet getoond worden — bv. de Checkmate/CIAO-kaart op .nl.
     *
     * @return list<string>
     */
    public static function hiddenDocSections(?Request $request = null): array
    {
        $request ??= request();
        $row = self::marketRow(self::normalizeHost($request->getHost()));
        if ($row === null || ! $row['live'] || $row['www']) {
            return [];
        }

        return $row['hidden_doc_sections'];
    }

    /**
     * FAQ-slugs (config/faq.php section_order) die op deze markt verborgen zijn.
     *
     * @return list<string>
     */
    public static function hiddenFaqItems(?Request $request = null): array
    {
        $request ??= request();
        $row = self::marketRow(self::normalizeHost($request->getHost()));
        if ($row === null || ! $row['live'] || $row['www']) {
            return [];
        }

        return $row['hidden_faq_items'];
    }

    /**
     * Vertaal-overlay voor de markt van het huidige request
     * ('nl' → lang/markets/nl/…), of null als er geen overlay geldt.
     */
    public static function marketOverlay(?Request $request = null): ?string
    {
        return self::marketKey($request);
    }

    /**
     * Verwijdert kaarten met een 'key' uit hidden_doc_sections uit een
     * geladen product_docs-content-array (left/right/full kolommen).
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function filterDocSections(array $content, ?Request $request = null): array
    {
        $hidden = self::hiddenDocSections($request);
        if ($hidden === []) {
            return $content;
        }

        foreach (['left', 'right', 'full'] as $column) {
            if (! isset($content[$column]) || ! is_array($content[$column])) {
                continue;
            }
            $content[$column] = array_values(array_filter(
                $content[$column],
                static fn ($card) => ! is_array($card)
                    || ! in_array($card['key'] ?? null, $hidden, true),
            ));
        }

        return $content;
    }

    /**
     * @return list<string>
     */
    public static function localesFor(?Request $request = null): array
    {
        $request ??= request();
        $row = self::marketRow(self::normalizeHost($request->getHost()));
        if ($row !== null && $row['live'] && ! $row['www']) {
            return $row['locales'];
        }

        $supported = config('locales.supported', []);

        return is_array($supported) ? array_values($supported) : [];
    }

    public static function defaultLocaleFor(?Request $request = null): string
    {
        $request ??= request();
        $row = self::marketRow(self::normalizeHost($request->getHost()));
        if ($row !== null && $row['live'] && ! $row['www']) {
            return $row['default_locale'];
        }

        return (string) config('locales.default', 'nl');
    }

    public static function openGraphLocale(?Request $request = null): string
    {
        $request ??= request();
        $locale = app()->getLocale();
        $region = self::regionFor($request, $locale);
        if ($region !== null) {
            return $locale.'_'.$region;
        }

        return str_replace('_', '-', $locale);
    }

    public static function inLanguage(?Request $request = null): ?string
    {
        $request ??= request();
        $region = self::regionFor($request, app()->getLocale());
        if ($region === null) {
            return null;
        }

        return app()->getLocale().'-'.$region;
    }

    /**
     * Login/register. Op een live marktdomein absoluut naar het app-domein,
     * anders de gewone route() zodat lokaal inloggen blijft werken.
     */
    public static function authUrl(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        if (self::isLiveMarketRequest()) {
            return 'https://'.self::appHost().$path;
        }

        return match ($path) {
            '/login' => route('login'),
            '/register' => route('register'),
            default => url($path),
        };
    }

    public static function hreflangAppOrigin(?Request $request = null): string
    {
        $request ??= request();
        $host = self::normalizeHost($request->getHost());
        if (self::isLocalAppHost($host)) {
            return $request->getSchemeAndHttpHost();
        }

        return 'https://'.self::appHost();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function absoluteRoute(string $origin, string $name, array $parameters = []): string
    {
        URL::forceRootUrl($origin);
        $scheme = parse_url($origin, PHP_URL_SCHEME);
        if (is_string($scheme) && $scheme !== '') {
            URL::forceScheme($scheme);
        }

        try {
            return route($name, $parameters, absolute: true);
        } finally {
            URL::forceRootUrl(null);
            URL::forceScheme(null);
        }
    }

    /**
     * @return list<array{host: string, locales: list<string>, regions: array<string, string>}>
     */
    public static function liveMarkets(): array
    {
        $markets = config('marketing.domains.markets', []);
        if (! is_array($markets)) {
            return [];
        }

        $live = [];
        foreach ($markets as $host => $row) {
            if (! is_array($row) || empty($row['live'])) {
                continue;
            }
            $regions = is_array($row['regions'] ?? null) ? $row['regions'] : [];
            $locales = is_array($row['locales'] ?? null) ? array_values($row['locales']) : [];
            $live[] = [
                'host' => self::normalizeHost((string) $host),
                'locales' => $locales,
                'regions' => $regions,
            ];
        }

        return $live;
    }

    /**
     * @return array{action: 'pass'}|array{action: 'missing'}|array{action: 'redirect', url: string, status: int}
     */
    public static function gate(Request $request): array
    {
        $host = self::normalizeHost($request->getHost());

        if (self::isLocalAppHost($host) || $host === self::appHost()) {
            return ['action' => 'pass'];
        }

        if ($host === 'www.'.self::appHost()) {
            return self::redirectOrMissing($request, self::appHost(), 301);
        }

        $row = self::marketRow($host);
        if ($row === null) {
            return ['action' => 'pass'];
        }

        if (! $row['live']) {
            return self::redirectOrMissing($request, self::appHost(), 302);
        }

        $status = $row['permanent_redirects'] ? 301 : 302;
        if ($row['www']) {
            return self::redirectOrMissing($request, $row['host'], $status);
        }

        $method = $request->getMethod();
        $path = self::path($request);
        $readable = $method === 'GET' || $method === 'HEAD';

        if ($readable && self::isPublicDocument($path)) {
            return ['action' => 'pass'];
        }

        if ($method === 'POST' && self::isTrackedPost($path)) {
            return ['action' => 'pass'];
        }

        if ($readable && str_starts_with($path, '/livewire/')) {
            return ['action' => 'pass'];
        }

        $split = self::splitLocale($path);
        if ($split !== null) {
            [$code, $suffix] = $split;
            if (! in_array($code, $row['locales'], true)) {
                return ['action' => 'missing'];
            }
            if (in_array($suffix, $row['hidden_paths'], true)) {
                return ['action' => 'missing'];
            }
            if (self::suffixAllowed($suffix)) {
                return $readable ? ['action' => 'pass'] : ['action' => 'missing'];
            }
        } elseif ($readable && self::isLegacyMarketing($path) && ! in_array($path, $row['hidden_paths'], true)) {
            return ['action' => 'pass'];
        }

        return self::redirectOrMissing($request, self::appHost(), $status);
    }

    public static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = explode('/', $host)[0];
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return rtrim($host, '.');
    }

    /**
     * @return array{action: 'missing'}|array{action: 'redirect', url: string, status: int}
     */
    private static function redirectOrMissing(Request $request, string $host, int $status): array
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return ['action' => 'missing'];
        }

        return [
            'action' => 'redirect',
            'url' => self::urlOnHost($request, $host),
            'status' => $status,
        ];
    }

    private static function urlOnHost(Request $request, string $host): string
    {
        $url = 'https://'.$host.self::path($request);
        $query = $request->query();
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $url;
    }

    private static function path(Request $request): string
    {
        $path = $request->getPathInfo();
        if ($path === '' || $path === '/') {
            return '/';
        }

        return rtrim($path, '/') ?: '/';
    }

    private static function isLocalAppHost(string $host): bool
    {
        return $host === 'localhost'
            || $host === '127.0.0.1'
            || str_ends_with($host, '.test');
    }

    private static function isPublicDocument(string $path): bool
    {
        if (in_array($path, ['/sitemap.xml', '/robots.txt', '/llms.txt', '/llms-full.txt', '/favicon.ico'], true)) {
            return true;
        }

        $key = trim((string) config('indexnow.key', ''));
        if ($key !== '' && $path === '/'.$key.'.txt') {
            return true;
        }

        foreach (['/build/', '/images/', '/video/', '/fonts/', '/icons/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function isTrackedPost(string $path): bool
    {
        return in_array($path, [
            '/livewire/update',
            '/promo/track/video',
            '/promo/track/engage',
        ], true);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private static function splitLocale(string $path): ?array
    {
        if (! preg_match('#^/([a-z]{2})(/.*)?$#', $path, $matches)) {
            return null;
        }

        $supported = config('locales.supported', []);
        if (! is_array($supported) || ! in_array($matches[1], $supported, true)) {
            return null;
        }

        $suffix = $matches[2] ?? '';
        if ($suffix === '') {
            $suffix = '/';
        }

        return [$matches[1], $suffix];
    }

    private static function suffixAllowed(string $suffix): bool
    {
        $suffixes = self::marketingSuffixes();
        if (isset($suffixes[$suffix])) {
            return true;
        }

        foreach (['/legal/', '/docs/', '/demo/'] as $prefix) {
            if (str_starts_with($suffix, $prefix)) {
                return true;
            }
        }

        return $suffix === '/demo';
    }

    private static function isLegacyMarketing(string $path): bool
    {
        if ($path === '/demo' || str_starts_with($path, '/demo/')) {
            return true;
        }

        if (isset(self::marketingSuffixes()[$path])) {
            return true;
        }

        if (str_starts_with($path, '/legal/') || str_starts_with($path, '/docs/')) {
            return true;
        }

        return in_array($path, ['/real_estate', '/vastgoed', '/facility', '/comparison'], true);
    }

    /**
     * @return array<string, true>
     */
    private static function marketingSuffixes(): array
    {
        if (self::$suffixes !== null) {
            return self::$suffixes;
        }

        $suffixes = ['/' => true];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if ($uri === '{locale}') {
                continue;
            }
            if (! str_starts_with($uri, '{locale}/')) {
                continue;
            }
            $suffix = '/'.substr($uri, strlen('{locale}/'));
            if (str_contains($suffix, '{')) {
                continue;
            }
            $suffixes[$suffix] = true;
        }

        self::$suffixes = $suffixes;

        return $suffixes;
    }

    /**
     * URI-suffix van een named route (bv. 'checkmate' → '/checkmate'),
     * of null voor routes zonder vaste suffix (parameters, geen {locale}-prefix).
     */
    private static function routeSuffix(string $routeName): ?string
    {
        $route = Route::getRoutes()->getByName($routeName);
        if ($route === null) {
            return null;
        }

        $uri = $route->uri();
        if (str_starts_with($uri, '{locale}/')) {
            $uri = substr($uri, strlen('{locale}/'));
        }

        if ($uri === '' || str_contains($uri, '{')) {
            return null;
        }

        return '/'.ltrim($uri, '/');
    }

    /**
     * @return array{host: string, www: bool, key: string|null, live: bool, permanent_redirects: bool, locales: list<string>, regions: array<string, string>, default_locale: string, hidden_paths: list<string>, hidden_doc_sections: list<string>, hidden_faq_items: list<string>}|null
     */
    private static function marketRow(string $host): ?array
    {
        $apex = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $markets = config('marketing.domains.markets', []);
        if (! is_array($markets) || ! isset($markets[$apex]) || ! is_array($markets[$apex])) {
            return null;
        }

        $row = $markets[$apex];
        $locales = is_array($row['locales'] ?? null) ? array_values($row['locales']) : [];
        $regions = is_array($row['regions'] ?? null) ? $row['regions'] : [];

        return [
            'host' => $apex,
            'www' => $host !== $apex,
            'key' => is_string($row['key'] ?? null) ? $row['key'] : null,
            'live' => (bool) ($row['live'] ?? false),
            'permanent_redirects' => (bool) ($row['permanent_redirects'] ?? false),
            'locales' => $locales,
            'regions' => $regions,
            'default_locale' => (string) ($row['default_locale'] ?? 'nl'),
            'hidden_paths' => is_array($row['hidden_paths'] ?? null) ? array_values($row['hidden_paths']) : [],
            'hidden_doc_sections' => is_array($row['hidden_doc_sections'] ?? null) ? array_values($row['hidden_doc_sections']) : [],
            'hidden_faq_items' => is_array($row['hidden_faq_items'] ?? null) ? array_values($row['hidden_faq_items']) : [],
        ];
    }

    private static function regionFor(Request $request, string $locale): ?string
    {
        $row = self::marketRow(self::normalizeHost($request->getHost()));
        if ($row === null || ! $row['live'] || $row['www']) {
            return null;
        }

        $region = $row['regions'][$locale] ?? null;

        return is_string($region) && $region !== '' ? $region : null;
    }
}
