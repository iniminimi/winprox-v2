<?php

declare(strict_types=1);

use App\Support\Marketing\JsonLd;

it('serveert about en feature-pagina\'s met JSON-LD', function () {
    $this->get(route('about', ['locale' => 'en']))
        ->assertOk()
        ->assertSee(__('about.title', [], 'en'))
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"SoftwareApplication"', false);

    foreach (['facility', 'time', 'esg', 'qr'] as $slug) {
        $response = $this->get(route('features.'.$slug, ['locale' => 'en']))
            ->assertOk()
            ->assertSee(__('features.'.$slug.'.title', [], 'en'));

        if ($slug === 'time') {
            $response->assertSee('images/welcome/winprox_time_module_logo.jpg', false)
                ->assertSee(__('features.time.logo_alt', [], 'en'));
        }
    }
});

it('redirect bare /api naar de API & Webhooks fiche', function () {
    $this->withHeader('Accept-Language', 'nl-BE,nl;q=0.9')
        ->get('/api')
        ->assertRedirect();

    $this->get('/nl/api')
        ->assertRedirect(route('product.api_webhooks', ['locale' => 'nl']));
});

it('redirect bare marketing feature-URL\'s naar locale-prefix', function () {
    $this->withHeader('Accept-Language', 'xx-XX,xx;q=0.9')
        ->get('/about')
        ->assertRedirect(route('about', ['locale' => 'nl']));

    $this->withHeader('Accept-Language', 'xx-XX,xx;q=0.9')
        ->get('/features/facility')
        ->assertRedirect(route('features.facility', ['locale' => 'nl']));

    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')
        ->get('/features/esg')
        ->assertRedirect(route('features.esg', ['locale' => 'de']));
});

it('bouwt FAQPage JSON-LD met vragen', function () {
    app()->setLocale('en');
    $graph = JsonLd::faqPage();

    expect($graph['@type'])->toBe('FAQPage')
        ->and($graph['mainEntity'])->not->toBeEmpty()
        ->and($graph['mainEntity'][0]['@type'])->toBe('Question');
});

it('toont FAQPage schema op publieke FAQ', function () {
    $this->get(route('faq.public', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('"@type":"FAQPage"', false);
});

it('llms.txt bevat about, features en API & Webhooks fiche', function () {
    $this->get(route('llms.txt'))
        ->assertOk()
        ->assertSee(route('about', ['locale' => 'en'], absolute: true), false)
        ->assertSee(route('features.facility', ['locale' => 'en'], absolute: true), false)
        ->assertSee(route('product.api_webhooks', ['locale' => 'en'], absolute: true), false);
});
