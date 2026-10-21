<?php

declare(strict_types=1);

use App\Support\Marketing\MarketingSeo;

it('rendert het tijdsregistratie-2027 artikel in elke locale', function () {
    foreach (['nl', 'en', 'fr', 'de', 'es', 'it'] as $locale) {
        $this->get(route('article.tijdsregistratie-2027', ['locale' => $locale]))
            ->assertOk()
            ->assertSee(__('articles.tijdsregistratie_2027.title', [], $locale), false)
            ->assertSee(__('articles.tijdsregistratie_2027.updated', [], $locale), false);
    }
});

it('redirect van niet-gelocaliseerde URL naar locale-prefix', function () {
    $this->withHeader('Accept-Language', 'xx-XX,xx;q=0.9')
        ->get('/tijdsregistratie-2027')
        ->assertRedirect(route('article.tijdsregistratie-2027', ['locale' => 'nl']));
});

it('zet FAQPage- en Article-JSON-LD op het artikel', function () {
    $response = $this->get(route('article.tijdsregistratie-2027', ['locale' => 'nl']));

    $response->assertOk()
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('"@type":"Article"', false)
        ->assertSee('canonical', false);
});

it('neemt het artikel op in sitemap en hreflang', function () {
    expect(MarketingSeo::indexedRouteNames())->toContain('article.tijdsregistratie-2027');
    expect(MarketingSeo::sitemapUrls())
        ->toContain(route('article.tijdsregistratie-2027', ['locale' => 'nl'], absolute: true));
});
