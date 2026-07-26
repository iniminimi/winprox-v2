<?php

declare(strict_types=1);

namespace App\Support\Marketing;

/**
 * SEO-helpers voor publieke marketingpagina's (locale-URL's, hreflang, sitemap).
 */
final class MarketingSeo
{
    /**
     * @return list<string>
     */
    public static function routeNames(): array
    {
        $names = ['welcome', 'promo', 'pricing', 'contact.index', 'faq.public'];

        foreach (config('legal.documents', []) as $meta) {
            if (isset($meta['route']) && is_string($meta['route'])) {
                $names[] = $meta['route'];
            }
        }

        return $names;
    }

    public static function isMarketingRoute(?string $name): bool
    {
        return $name !== null && in_array($name, self::routeNames(), true);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return list<array{hreflang: string, href: string}>
     */
    public static function alternateLinks(?string $routeName = null, array $parameters = []): array
    {
        $routeName ??= request()->route()?->getName();
        if (! self::isMarketingRoute($routeName)) {
            return [];
        }

        $parameters = $parameters !== [] ? $parameters : self::routeParametersWithoutLocale();
        $supported = config('locales.supported', []);
        $default = (string) config('locales.default', 'nl');
        $links = [];

        foreach ($supported as $locale) {
            $links[] = [
                'hreflang' => $locale,
                'href' => route($routeName, array_merge($parameters, ['locale' => $locale]), absolute: true),
            ];
        }

        $links[] = [
            'hreflang' => 'x-default',
            'href' => route($routeName, array_merge($parameters, ['locale' => $default]), absolute: true),
        ];

        return $links;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function canonicalUrl(?string $routeName = null, array $parameters = []): ?string
    {
        $routeName ??= request()->route()?->getName();
        if (! self::isMarketingRoute($routeName)) {
            return null;
        }

        $parameters = $parameters !== [] ? $parameters : self::routeParametersWithoutLocale();
        $locale = (string) (request()->route('locale') ?? app()->getLocale());

        return route($routeName, array_merge($parameters, ['locale' => $locale]), absolute: true);
    }

    public static function switchUrl(string $targetLocale): ?string
    {
        $route = request()->route();
        $name = $route?->getName();
        if (! self::isMarketingRoute($name)) {
            return null;
        }

        $supported = config('locales.supported', []);
        if (! in_array($targetLocale, $supported, true)) {
            return null;
        }

        $params = $route->parameters();
        $params['locale'] = $targetLocale;
        $url = route($name, $params);

        $query = request()->query();
        unset($query['lang']);

        if ($query === []) {
            return $url;
        }

        return $url.'?'.http_build_query($query);
    }

    /**
     * @return list<string>
     */
    public static function sitemapUrls(): array
    {
        $urls = [];
        $supported = config('locales.supported', []);

        foreach (self::routeNames() as $routeName) {
            foreach ($supported as $locale) {
                $urls[] = route($routeName, ['locale' => $locale], absolute: true);
            }
        }

        return $urls;
    }

    /**
     * @return array<string, mixed>
     */
    private static function routeParametersWithoutLocale(): array
    {
        $params = request()->route()?->parameters() ?? [];
        unset($params['locale']);

        return $params;
    }
}
