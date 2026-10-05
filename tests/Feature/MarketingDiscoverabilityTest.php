<?php

declare(strict_types=1);

use App\Support\Faq\FaqSections;
use App\Support\Marketing\JsonLd;

it('serveert about en feature-pagina\'s met JSON-LD', function () {
    $this->get(route('about', ['locale' => 'en']))
        ->assertOk()
        ->assertSee(__('about.title', [], 'en'))
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"SoftwareApplication"', false);

    $this->get(route('about', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee('Op de eigen vestiging', false)
        ->assertSee('Bij de klant', false)
        ->assertDontSee('Ziekenhuizen en zorgcampussen', false)
        ->assertDontSee('Vastgoed en vastgoedbeheer', false);

    foreach (['facility', 'time', 'esg', 'qr'] as $slug) {
        $response = $this->get(route('features.'.$slug, ['locale' => 'en']))
            ->assertOk()
            ->assertSee(__('features.'.$slug.'.title', [], 'en'));

        if ($slug === 'time') {
            $response->assertSee('images/welcome/winprox_time_module_logo.jpg', false)
                ->assertSee(__('features.time.logo_alt', [], 'en'))
                ->assertDontSee('name="robots" content="noindex, follow"', false);
        } else {
            $response->assertSee('name="robots" content="noindex, follow"', false);
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

it('spiegelt FAQPage JSON-LD de zichtbare FAQ-items (vraag + antwoord + volgorde)', function () {
    app()->setLocale('en');
    $graph = JsonLd::faqPage();
    $items = FaqSections::orderedItems();

    $names = array_map(fn (array $entity): string => $entity['name'], $graph['mainEntity']);
    $titles = array_map(fn (array $item): string => $item['title'], $items);

    expect($names)->toBe($titles)
        ->and($graph['mainEntity'])->toHaveCount(count($items));
});

it('bevat FAQPage-antwoorden de volledige zichtbare body, niet de samenvatting', function () {
    app()->setLocale('en');
    $graph = JsonLd::faqPage();
    $byName = collect($graph['mainEntity'])->keyBy('name');

    $normalize = fn (string $value): string => trim(preg_replace('/\s+/u', ' ', $value) ?? '');

    // text-type: het volledige body-veld moet in het antwoord zitten.
    $textItem = collect(FaqSections::orderedItems())->firstWhere('slug', 'qr_code');
    $answer = $normalize($byName[$textItem['title']]['acceptedAnswer']['text']);
    expect($answer)->toContain($normalize($textItem['body']));

    // steps-type: intro + eerste stap + box horen in het antwoord.
    $stepsItem = collect(FaqSections::orderedItems())->firstWhere('slug', 'how_it_works');
    $answer = $normalize($byName[$stepsItem['title']]['acceptedAnswer']['text']);
    expect($answer)
        ->toContain($normalize($stepsItem['intro']))
        ->toContain($normalize($stepsItem['steps'][0]['title']))
        ->toContain($normalize($stepsItem['steps'][0]['text']))
        ->toContain($normalize($stepsItem['box_body']));
});

it('toont FAQPage schema op publieke FAQ', function () {
    $this->get(route('faq.public', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('"@type":"FAQPage"', false);
});

it('promoveert de homepage-headline tot H1 met prikklok-signalen', function () {
    $this->get(route('welcome', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee('<h1 class="wp-welcome-hero-minimal__headline">', false)
        ->assertSee('digitale prikklok', false)
        ->assertSee('<title>WinProx — Digitale prikklok en urenregistratie</title>', false);
});

it('stuurt SoftwareApplication JSON-LD in de pagina-taal', function () {
    $this->get(route('about', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee('"url":"'.route('welcome', ['locale' => 'nl'], absolute: true).'"', false)
        ->assertSee('"description":"Medewerkers prikken met hun telefoon', false);

    $this->get(route('about', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('"description":"Staff clock in with their phone', false);
});

it('linkt het Time-FAQ-item contextueel naar de prikklok-landing', function () {
    $this->get(route('faq.public', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee('/nl/prikklok', false);
});

it('llms.txt bevat about, feature-pagina\'s en Markdown-fiches', function () {
    $this->get(route('llms.txt'))
        ->assertOk()
        ->assertSee(route('about', ['locale' => 'en'], absolute: true), false)
        ->assertSee(route('prikklok', ['locale' => 'en'], absolute: true), false)
        ->assertSee(route('features.time', ['locale' => 'en'], absolute: true), false)
        ->assertDontSee(route('work-on-location', ['locale' => 'en'], absolute: true), false)
        ->assertDontSee(route('features.facility', ['locale' => 'en'], absolute: true), false)
        ->assertSee(route('product.api_webhooks.md', ['locale' => 'en'], absolute: true), false)
        ->assertSee(route('product.features.md', ['locale' => 'en'], absolute: true), false)
        ->assertSee(route('product.technical.md', ['locale' => 'en'], absolute: true), false)
        ->assertSee(route('legal.dpa.md', ['locale' => 'en'], absolute: true), false)
        ->assertSee(url('/llms-full.txt'), false);
});
