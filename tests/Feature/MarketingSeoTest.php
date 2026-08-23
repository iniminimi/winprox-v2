<?php

declare(strict_types=1);

use App\Support\Marketing\MarketingSeo;
use App\Support\Marketing\PromoLandingUrl;
use Illuminate\Support\Facades\URL;

it('gebruikt unieke locale-URL voor marketingpagina\'s', function () {
    expect(route('welcome', ['locale' => 'de'], absolute: false))->toBe('/de');
    expect(route('promo', ['locale' => 'fr'], absolute: false))->toBe('/fr/promo');
    expect(route('government', ['locale' => 'fr'], absolute: false))->toBe('/fr/government');
    expect(route('hospitality', ['locale' => 'nl'], absolute: false))->toBe('/nl/hospitality');
    expect(route('realestate', ['locale' => 'nl'], absolute: false))->toBe('/nl/realestate');
    expect(route('pricing', ['locale' => 'en'], absolute: false))->toBe('/en/pricing');
    expect(route('contact.index', ['locale' => 'es'], absolute: false))->toBe('/es/contact');
    expect(route('legal.privacy', ['locale' => 'it'], absolute: false))->toBe('/it/legal/privacy');
});

it('redirect van oude marketing-URL\'s naar locale-prefix', function () {
    $this->withHeader('Accept-Language', 'xx-XX,xx;q=0.9')
        ->get('/')
        ->assertRedirect(route('welcome', ['locale' => 'nl']));

    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')
        ->get('/')
        ->assertRedirect(route('welcome', ['locale' => 'de']));

    $this->get('/promo?lang=fr&ref=prm_test')
        ->assertRedirect(route('promo', ['locale' => 'fr', 'ref' => 'prm_test']));

    $this->withHeader('Accept-Language', 'xx-XX,xx;q=0.9')
        ->get('/pricing')
        ->assertRedirect(route('pricing', ['locale' => 'nl']));

    $this->withHeader('Accept-Language', 'xx-XX,xx;q=0.9')
        ->get('/contact')
        ->assertRedirect(route('contact.index', ['locale' => 'nl']));

    $this->withHeader('Accept-Language', 'xx-XX,xx;q=0.9')
        ->get('/legal/privacy')
        ->assertRedirect(route('legal.privacy', ['locale' => 'nl']));
});

it('zet hreflang en canonical op welcome', function () {
    $this->get(route('welcome', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('rel="canonical"', false)
        ->assertSee('hreflang="nl"', false)
        ->assertSee('hreflang="en"', false)
        ->assertSee('hreflang="x-default"', false)
        ->assertSee(route('welcome', ['locale' => 'de'], absolute: true), false);
});

it('levert sitemap met alle taalvarianten', function () {
    $response = $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $body = $response->getContent();
    expect($body)->toContain('<?xml version="1.0"')
        ->and($body)->toContain(route('welcome', ['locale' => 'nl'], absolute: true))
        ->and($body)->toContain(route('government', ['locale' => 'fr'], absolute: true))
        ->and($body)->toContain(route('hospitality', ['locale' => 'nl'], absolute: true))
        ->and($body)->toContain(route('realestate', ['locale' => 'nl'], absolute: true))
        ->and($body)->toContain('hreflang="x-default"');

    $localeCount = count(config('locales.supported'));
    $routeCount = count(MarketingSeo::routeNames());
    expect(substr_count($body, '<url>'))->toBe($localeCount * $routeCount);
});

it('promo-landing-url gebruikt locale in het pad', function () {
    expect(PromoLandingUrl::forRecipientTokenOnBaseUrl('prm_4cfe5ddb16702059', 'https://winprox.app', 'fr'))
        ->toBe('https://winprox.app/fr/government?ref=prm_4cfe5ddb16702059');

    expect(PromoLandingUrl::anonymous('de'))
        ->toBe(route('government', ['locale' => 'de'], absolute: true));
});

it('taalwissel op marketing gaat naar andere locale-URL', function () {
    URL::defaults(['locale' => 'nl']);

    $this->get(route('welcome', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee('href="'.route('welcome', ['locale' => 'fr']).'"', false)
        ->assertDontSee('href="'.route('locale.switch', 'fr').'"', false);
});
