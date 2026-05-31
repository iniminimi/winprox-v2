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

it('valt terug op de standaardlocale (nl) zonder keuze', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(__('auth.submit', [], 'nl'));
});
