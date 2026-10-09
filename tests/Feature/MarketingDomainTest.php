<?php

declare(strict_types=1);

use App\Models\PromoRecipient;
use App\Models\PromoVideoPlay;
use App\Models\PromoVisit;
use App\Models\User;
use App\Support\Marketing\MarketingDomain;
use App\Support\Marketing\MarketingSeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

function winproxEnableMarket(string $host, bool $live, bool $permanent = false): void
{
    $markets = config('marketing.domains.markets');
    expect($markets)->toBeArray()->and($markets)->toHaveKey($host);
    $markets[$host]['live'] = $live;
    $markets[$host]['permanent_redirects'] = $permanent;
    config(['marketing.domains.markets' => $markets]);
}

/**
 * @return array<string, string>
 */
function winproxHreflangMap(string $html): array
{
    preg_match_all('/<link rel="alternate" hreflang="([^"]+)" href="([^"]+)"/', $html, $matches, PREG_SET_ORDER);
    $map = [];
    foreach ($matches as $match) {
        $map[$match[1]] = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return $map;
}

function winproxCanonical(string $html): string
{
    preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $match);

    return html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

it('houdt .be en .nl dicht zolang ze niet live zijn', function () {
    expect(config('marketing.domains.markets')['winprox.be']['live'])->toBeFalse()
        ->and(config('marketing.domains.markets')['winprox.nl']['live'])->toBeFalse();

    $this->get('https://winprox.app/nl')
        ->assertOk()
        ->assertDontSee('winprox.be', false)
        ->assertDontSee('winprox.nl', false);

    $this->get('https://winprox.be/nl/pricing')
        ->assertStatus(302)
        ->assertRedirect('https://winprox.app/nl/pricing');

    $this->get('https://www.winprox.be/nl/pricing')
        ->assertStatus(302)
        ->assertRedirect('https://winprox.app/nl/pricing');

    $this->get('https://winprox.nl/')
        ->assertStatus(302)
        ->assertRedirect('https://winprox.app/');

    $this->post('https://winprox.be/stripe/webhook', ['type' => 'ping'])->assertNotFound();
    $this->post('https://www.winprox.nl/stripe/webhook', ['type' => 'ping'])->assertNotFound();
});

it('negeert permanent zolang de markt niet live is', function () {
    winproxEnableMarket('winprox.be', false, true);

    $this->get('https://winprox.be/login?next=1')
        ->assertStatus(302)
        ->assertRedirect('https://winprox.app/login?next=1');

    $this->get('https://www.winprox.be/login')
        ->assertStatus(302)
        ->assertRedirect('https://winprox.app/login');
});

it('houdt een live .be-site op 302 tot de permanente schakelaar omgaat', function () {
    winproxEnableMarket('winprox.be', true, false);

    $this->get('https://winprox.be/login')
        ->assertStatus(302)
        ->assertRedirect('https://winprox.app/login');

    $this->get('https://www.winprox.be/nl/pricing')
        ->assertStatus(302)
        ->assertRedirect('https://winprox.be/nl/pricing');

    $this->post('https://winprox.be/login')->assertNotFound();
    $this->post('https://www.winprox.be/stripe/webhook', ['type' => 'ping'])->assertNotFound();
    $this->get('https://winprox.be/de')->assertNotFound();
    $this->get('https://winprox.be/en/pricing')->assertNotFound();

    $this->get('https://winprox.be/pricing')
        ->assertStatus(301)
        ->assertRedirect('https://winprox.be/nl/pricing');
});

it('maakt app-paden en www definitief zodra permanent aanstaat', function () {
    winproxEnableMarket('winprox.be', true, true);

    $this->get('https://winprox.be/dashboard')
        ->assertStatus(301)
        ->assertRedirect('https://winprox.app/dashboard');

    $this->get('https://www.winprox.be/fr')
        ->assertStatus(301)
        ->assertRedirect('https://winprox.be/fr');

    $this->post('https://winprox.be/stripe/webhook', ['type' => 'ping'])->assertNotFound();
});

it('kiest op .be alleen nl of fr en stuurt een ingelogde bezoeker niet naar het dashboard', function () {
    winproxEnableMarket('winprox.be', true);

    $this->withHeader('Accept-Language', 'fr-BE,fr;q=0.9')
        ->get('https://winprox.be/')
        ->assertStatus(302)
        ->assertRedirect('https://winprox.be/fr');

    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')
        ->get('https://winprox.be/')
        ->assertStatus(302)
        ->assertRedirect('https://winprox.be/nl');

    $this->withSession(['locale' => 'de'])
        ->get('https://winprox.be/')
        ->assertStatus(302)
        ->assertRedirect('https://winprox.be/nl');

    $this->withCookie('locale', 'de')
        ->get('https://winprox.be/')
        ->assertStatus(302)
        ->assertRedirect('https://winprox.be/nl');

    $this->actingAs(User::factory()->superuser()->create());

    $this->get('https://winprox.be/')
        ->assertStatus(302)
        ->assertRedirect('https://winprox.be/nl');

    $this->get('https://winprox.be/nl')
        ->assertOk()
        ->assertDontSee('https://winprox.be/login', false)
        ->assertSee('https://winprox.app/login', false)
        ->assertSee('https://winprox.app/register', false);
});

it('zet dezelfde hreflang-set op .app en .be, en geen .nl tot die markt live is', function () {
    winproxEnableMarket('winprox.be', true);

    $expectedKeys = ['de', 'en', 'es', 'fr', 'fr-BE', 'it', 'nl', 'nl-BE', 'x-default'];
    sort($expectedKeys);

    /** @var array<string, array<string, string>> $maps */
    $maps = [];
    $test = $this;
    $fetch = function (string $url) use (&$maps, $test, $expectedKeys): array {
        if (isset($maps[$url])) {
            return $maps[$url];
        }

        $response = $test->get($url);
        $response->assertOk();
        $html = $response->getContent();
        expect(substr_count($html, 'hreflang="x-default"'))->toBe(1);
        expect(winproxCanonical($html))->toBe($url);

        $map = winproxHreflangMap($html);
        $keys = array_keys($map);
        sort($keys);
        expect($keys)->toBe($expectedKeys);
        expect($map['x-default'])->toBe($map['nl'])
            ->and(parse_url((string) $map['nl'], PHP_URL_HOST))->toBe('winprox.app')
            ->and(parse_url((string) $map['nl-BE'], PHP_URL_HOST))->toBe('winprox.be')
            ->and(parse_url((string) $map['fr-BE'], PHP_URL_HOST))->toBe('winprox.be')
            ->and(parse_url((string) $map['nl-BE'], PHP_URL_PATH))->toBe(parse_url((string) $map['nl'], PHP_URL_PATH))
            ->and(parse_url((string) $map['fr-BE'], PHP_URL_PATH))->toBe(parse_url((string) $map['fr'], PHP_URL_PATH))
            ->and($map)->not->toHaveKey('nl-NL');

        $maps[$url] = $map;

        return $map;
    };

    foreach (MarketingSeo::indexedRouteNames() as $name) {
        $beNl = MarketingDomain::absoluteRoute('https://winprox.be', $name, ['locale' => 'nl']);
        $map = $fetch($beNl);
        $fetch(MarketingDomain::absoluteRoute('https://winprox.app', $name, ['locale' => 'nl']));
        $fetch(MarketingDomain::absoluteRoute('https://winprox.be', $name, ['locale' => 'fr']));

        foreach ($map as $href) {
            $other = $fetch($href);
            expect($other['nl-BE'])->toBe($beNl);
        }
    }
});

it('neemt .nl op in hreflang zodra alleen die config live staat', function () {
    winproxEnableMarket('winprox.nl', true);

    $html = $this->get('https://winprox.app/nl')->assertOk()->getContent();
    $map = winproxHreflangMap($html);

    expect($map)->toHaveKey('nl-NL')
        ->and($map['nl-NL'])->toBe('https://winprox.nl/nl')
        ->and($map)->not->toHaveKey('nl-BE');
});

it('schrijft og:locale en inLanguage alleen op het live marktdomein', function () {
    winproxEnableMarket('winprox.be', true);

    $be = $this->get('https://winprox.be/nl')->assertOk()->getContent();
    expect($be)->toContain('property="og:locale" content="nl_BE"')
        ->and($be)->toContain('"inLanguage":"nl-BE"');

    $fr = $this->get('https://winprox.be/fr')->assertOk()->getContent();
    expect($fr)->toContain('property="og:locale" content="fr_BE"')
        ->and($fr)->toContain('"inLanguage":"fr-BE"');

    $app = $this->get('https://winprox.app/nl')->assertOk()->getContent();
    expect($app)->toContain('property="og:locale" content="nl"')
        ->and($app)->not->toContain('nl_BE')
        ->and($app)->not->toContain('inLanguage');
});

it('toont op .be alleen nl en fr in de taalschakelaar en houdt login op .app', function () {
    winproxEnableMarket('winprox.be', true);

    $welcome = $this->get('https://winprox.be/nl')->assertOk()->getContent();
    preg_match_all('/<details class="wp-lang-select.*?<\/details>/s', $welcome, $menus);
    expect($menus[0])->not->toBeEmpty();
    foreach ($menus[0] as $menu) {
        preg_match_all('/href="([^"]+)"/', $menu, $hrefs);
        $hrefs = $hrefs[1];
        expect($hrefs)->not->toBeEmpty()
            ->and(implode(' ', $hrefs))->toContain('/fr')
            ->and(implode(' ', $hrefs))->not->toContain('winprox.app');
        foreach ($hrefs as $href) {
            expect($href)->not->toMatch('#/(de|en|es|it)(/|$)#');
        }
    }

    $landing = $this->get('https://winprox.be/nl/prikklok')->assertOk()->getContent();
    expect($landing)->toContain('https://winprox.app/login')
        ->and($landing)->toContain('https://winprox.app/register')
        ->and($landing)->not->toContain('https://winprox.be/login');
});

it('beperkt sitemap, robots en llms tot de talen van het domein', function () {
    winproxEnableMarket('winprox.be', true);

    $sitemap = $this->get('https://winprox.be/sitemap.xml')->assertOk()->getContent();
    expect(substr_count($sitemap, '<loc>'))->toBe(count(MarketingSeo::indexedRouteNames()) * 2)
        ->and($sitemap)->toContain('hreflang="nl-BE" href="https://winprox.be/nl"')
        ->and($sitemap)->toContain('hreflang="en" href="https://winprox.app/en"')
        ->and($sitemap)->not->toContain('https://winprox.be/de')
        ->and($sitemap)->not->toContain('https://winprox.be/en')
        ->and($sitemap)->not->toContain('/hospitality');

    $this->get('https://winprox.be/nl/hospitality')
        ->assertOk()
        ->assertSee('noindex', false);

    $this->get('https://winprox.be/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: https://winprox.be/sitemap.xml', false);

    $llms = $this->get('https://winprox.be/llms.txt')->assertOk()->getContent();
    expect($llms)->toContain('https://winprox.be/nl')
        ->and($llms)->toContain('https://winprox.be/fr')
        ->and($llms)->toContain('https://winprox.app/login')
        ->and($llms)->toContain('https://winprox.app/register')
        ->and($llms)->not->toContain('https://winprox.be/de')
        ->and($llms)->not->toContain('https://winprox.be/en');
});

it('telt een promo-klik op .be alleen na een bezoek met ref op dat domein', function () {
    winproxEnableMarket('winprox.be', true);

    $owner = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_2222222222222222',
        'label' => 'Be domain',
        'note' => null,
        'created_by' => $owner->id,
    ]);

    $this->postJson('https://winprox.be/promo/track/video', ['video_key' => 'prikklok'])
        ->assertNotFound();
    $this->postJson('https://winprox.be/promo/track/engage', ['page' => 'welcome'])
        ->assertNotFound();

    $this->get('https://winprox.app/nl?ref='.$recipient->token)->assertOk();
    session()->forget('promo_recipient_token');

    $this->postJson('https://winprox.be/promo/track/video', ['video_key' => 'prikklok'])
        ->assertNotFound();

    $this->get('https://winprox.be/nl?ref='.$recipient->token)->assertOk();

    $this->postJson('https://winprox.be/promo/track/video', ['video_key' => 'prikklok'])
        ->assertNoContent();
    $this->postJson('https://winprox.be/promo/track/engage', ['page' => 'welcome'])
        ->assertNoContent();

    expect(PromoVideoPlay::query()->count())->toBe(1)
        ->and(PromoVisit::query()->where('kind', 'engaged')->count())->toBe(1);
});

it('geeft een gast op .be geen livewire-data terug', function () {
    winproxEnableMarket('winprox.be', true);

    $response = $this->postJson('https://winprox.be/livewire/update', [
        'components' => [[
            'snapshot' => '{}',
            'updates' => [],
            'calls' => [],
        ]],
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->isRedirection())->toBeFalse()
        ->and($response->getContent())->not->toContain('notify_on_new_issue_email');
});

it('laat op een live .be alleen de allowlist door en stuurt de rest weg', function () {
    winproxEnableMarket('winprox.be', true);

    foreach ([
        '/login',
        '/register',
        '/dashboard',
        '/forgot-password',
        '/welcome-1995',
        '/platform',
        '/time/aaaaaaaaaaaaaaaaaaaa',
        '/cp/aaaaaaaaaaaaaaaaaaaa',
        '/melden/aaaaaaaaaaaaaaaaaaaa',
        '/auth/microsoft/redirect',
        '/report/aaaaaaaa',
    ] as $path) {
        $gate = MarketingDomain::gate(Request::create('https://winprox.be'.$path, 'GET'));
        expect($gate['action'])->toBe('redirect', $path);

        $this->get('https://winprox.be'.$path)
            ->assertStatus(302)
            ->assertRedirect('https://winprox.app'.$path);
    }

    expect(MarketingDomain::gate(Request::create('https://winprox.be/de', 'GET'))['action'])->toBe('missing')
        ->and(MarketingDomain::gate(Request::create('https://winprox.be/stripe/webhook', 'POST'))['action'])->toBe('missing')
        ->and(MarketingDomain::gate(Request::create('https://winprox.be/nl', 'GET'))['action'])->toBe('pass');

    $seen = [];
    foreach (Route::getRoutes() as $route) {
        $uri = '/'.ltrim($route->uri(), '/');
        $uri = preg_replace('/\{[^}]+\}/', 'aaaaaaaa', $uri) ?? $uri;
        foreach ($route->methods() as $method) {
            if ($method === 'HEAD') {
                continue;
            }
            $key = $method.' '.$uri;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $gate = MarketingDomain::gate(Request::create('https://winprox.be'.$uri, $method));
            if ($gate['action'] === 'pass') {
                continue;
            }

            $response = $this->call($method, 'https://winprox.be'.$uri);
            $status = $response->getStatusCode();
            expect(in_array($status, [301, 302, 404], true))->toBeTrue("{$method} {$uri} gaf {$status}");
            if ($response->isRedirect()) {
                expect((string) $response->headers->get('Location'))->toStartWith('https://winprox.app/');
            }
        }
    }
});
