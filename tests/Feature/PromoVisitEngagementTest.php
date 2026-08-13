<?php

use App\Enums\PromoVisitFollowKey;
use App\Enums\PromoVisitKind;
use App\Enums\PromoVisitPage;
use App\Models\PromoRecipient;
use App\Models\PromoVisit;
use App\Models\User;

it('logt een bevestigd welcome-bezoek via de engage-route', function () {
    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_aaaaaaaaaaaaaaaa',
        'label' => 'Hotel Engage',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $this->withSession(['promo_recipient_token' => $recipient->token])
        ->postJson(route('promo.track.engage'), ['page' => PromoVisitPage::Welcome->value])
        ->assertNoContent();

    expect(PromoVisit::query()->where('promo_recipient_id', $recipient->id)->count())->toBe(1)
        ->and(PromoVisit::query()->where('kind', PromoVisitKind::Engaged->value)->value('page'))
        ->toBe(PromoVisitPage::Welcome);
});

it('weigert engage-tracking zonder bestemmeling-sessie', function () {
    $this->postJson(route('promo.track.engage'), ['page' => PromoVisitPage::Welcome->value])
        ->assertNotFound();
});

it('logt doorklik naar contact wanneer de ref-sessie bestaat', function () {
    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_bbbbbbbbbbbbbbbb',
        'label' => 'Hotel Contact',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $this->withSession(['promo_recipient_token' => $recipient->token])
        ->get(route('contact.index', ['locale' => 'nl']))
        ->assertOk();

    expect(PromoVisit::query()->where('promo_recipient_id', $recipient->id)->count())->toBe(1)
        ->and(PromoVisit::query()->where('kind', PromoVisitKind::Follow->value)->value('follow_key'))
        ->toBe(PromoVisitFollowKey::Contact);
});

it('logt geen doorklik zonder ref-sessie', function () {
    $this->get(route('contact.index', ['locale' => 'nl']))
        ->assertOk();

    expect(PromoVisit::query()->count())->toBe(0);
});

it('zet engage-url op welcome bij geldige ref', function () {
    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_cccccccccccccccc',
        'label' => 'Hotel Welcome',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $this->get('/nl/?ref='.$recipient->token)
        ->assertOk()
        ->assertSee('data-promo-engage-url', false)
        ->assertSee('csrf-token', false);
});
