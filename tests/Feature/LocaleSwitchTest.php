<?php

it('bewaart een geldige locale in de sessie', function () {
    $this->get('/locale/fr')->assertSessionHas('locale', 'fr');
});

it('negeert een onbekende locale', function () {
    $this->get('/locale/fr')->assertSessionHas('locale', 'fr');
    $this->get('/locale/zz')->assertSessionHas('locale', 'fr');
});

it('past de gekozen locale toe via de middleware', function () {
    $this->withSession(['locale' => 'fr'])
        ->get(route('login'))
        ->assertOk()
        ->assertSee(__('auth.submit', [], 'fr'));
});

it('werkt voor ingelogde gebruikers via zijbalk en slaat locale op de gebruiker op', function () {
    $user = \App\Models\User::factory()->create(['locale' => 'nl']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('dashboard.kpi.locations', [], 'nl'));

    $this->actingAs($user)
        ->get('/locale/en')
        ->assertSessionHas('locale', 'en');

    expect($user->fresh()->locale)->toBe('en');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('dashboard.kpi.locations', [], 'en'))
        ->assertDontSee(__('dashboard.kpi.locations', [], 'nl'));
});

it('valt terug op de standaardlocale (nl) zonder keuze', function () {
    $this->withHeader('Accept-Language', 'xx-XX,xx;q=0.9')
        ->get(route('login'))
        ->assertOk()
        ->assertSee(__('auth.submit', [], 'nl'));
});

it('gebruikt browser taalkeuze als geen sessie/cookie/gebruiker locale', function () {
    $this->withHeader('Accept-Language', 'fr-BE,fr;q=0.9,en;q=0.8')
        ->get(route('login'))
        ->assertOk()
        ->assertSee(__('auth.submit', [], 'fr'));
});

it('valt terug op default als browser taal niet supported is', function () {
    $this->withHeader('Accept-Language', 'pt-PT,pt;q=0.9')
        ->get(route('login'))
        ->assertOk()
        ->assertSee(__('auth.submit', [], 'nl'));
});

it('gebruikt browser Spaans wanneer es wordt aangeboden', function () {
    $this->withHeader('Accept-Language', 'es-ES,es;q=0.9')
        ->get(route('login'))
        ->assertOk()
        ->assertSee(__('auth.submit', [], 'es'));
});

it('cookie prevaleert boven browser taalkeuze', function () {
    $this->withCookie('locale', 'de')
        ->withHeader('Accept-Language', 'fr-BE,fr;q=0.9')
        ->get(route('login'))
        ->assertOk()
        ->assertSee(__('auth.submit', [], 'de'));
});
