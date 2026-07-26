<?php

declare(strict_types=1);

use App\Support\Marketing\JsonLd;

it('serveert about, api-intro en feature-pagina\'s met JSON-LD', function () {
    $this->get(route('about', ['locale' => 'en']))
        ->assertOk()
        ->assertSee(__('about.title', [], 'en'))
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"SoftwareApplication"', false);

    $this->get(route('api.public', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee(__('api_public.title', [], 'nl'))
        ->assertSee('"@type":"SoftwareApplication"', false);

    foreach (['facility', 'time', 'esg', 'qr'] as $slug) {
        $this->get(route('features.'.$slug, ['locale' => 'en']))
            ->assertOk()
            ->assertSee(__('features.'.$slug.'.title', [], 'en'));
    }
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

it('llms.txt bevat about en feature-URL\'s', function () {
    $this->get(route('llms.txt'))
        ->assertOk()
        ->assertSee(route('about', ['locale' => 'en'], absolute: true), false)
        ->assertSee(route('features.facility', ['locale' => 'en'], absolute: true), false)
        ->assertSee(route('api.public', ['locale' => 'en'], absolute: true), false);
});
