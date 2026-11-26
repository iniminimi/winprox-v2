<?php

it('toont Sense en Trace apart en laat de prikklok alleen verwijzen', function () {
    $this->withHeader('Accept-Language', 'xx-XX,xx;q=0.9')
        ->get('/prikklok/sense')
        ->assertRedirect(route('clock.sense', ['locale' => 'nl']));

    $this->get(route('clock.sense', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee(__('clock_sense.title', [], 'nl'))
        ->assertSee(__('clock_sense.lead', [], 'nl'))
        ->assertSee('href="'.route('clock.trace', ['locale' => 'nl']).'"', false)
        ->assertSee('href="'.route('prikklok', ['locale' => 'nl']).'"', false)
        ->assertDontSee('Seeed', false)
        ->assertDontSee('crèche', false);

    $this->get(route('clock.trace', ['locale' => 'fr']))
        ->assertOk()
        ->assertSee(__('trace.lead', [], 'fr'))
        ->assertSee(__('trace.privacy_body', [], 'fr'))
        ->assertSee('href="'.route('clock.sense', ['locale' => 'fr']).'"', false)
        ->assertDontSee('Seeed', false)
        ->assertDontSee('crèche', false)
        ->assertDontSee('Camerawet', false);

    $this->get(route('prikklok', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee(__('landings.prikklok.models.title', [], 'nl'))
        ->assertSee('href="'.route('clock.sense', ['locale' => 'nl']).'"', false)
        ->assertSee('href="'.route('clock.trace', ['locale' => 'nl']).'"', false);

    $this->get(route('product.features', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee('href="'.route('clock.sense', ['locale' => 'nl']).'"', false)
        ->assertSee('href="'.route('clock.trace', ['locale' => 'nl']).'"', false)
        ->assertDontSee('boven 900', false);
});
