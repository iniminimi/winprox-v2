<?php

use App\Enums\PromoLanding;
use App\Enums\PromoVisitPage;
use App\Livewire\Platform\PromoRecipients;
use App\Models\MunicipalPromoEmailSend;
use App\Models\PromoRecipient;
use App\Models\PromoVideoPlay;
use App\Models\PromoVisit;
use App\Models\User;
use App\Enums\MunicipalPromoEmailSendStatus;
use Livewire\Livewire;

it('stuurt /promo door naar de overheid-landing', function () {
    $this->get(route('promo'))
        ->assertRedirect(route('government'));
});

it('stuurt /promo met ref door naar de campagne-landing', function () {
    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_1111111111111111',
        'label' => 'Hotel Redirect',
        'note' => null,
        'created_by' => $superuser->id,
    ]);
    $campaign = app(\App\Actions\Marketing\CreatePromoCampaignAction::class)->handle(
        slug: 'hospitality-redirect',
        name: 'Hospitality redirect',
        locale: 'nl',
        actorUserId: (int) $superuser->id,
        landing: PromoLanding::Hospitality,
    );
    $import = \App\Models\PromoCampaignImport::query()->create([
        'promo_campaign_id' => $campaign->id,
        'original_filename' => 'one.xlsx',
        'row_count' => 1,
        'imported_by' => $superuser->id,
        'imported_at' => now(),
    ]);
    \App\Models\PromoCampaignTarget::query()->create([
        'promo_campaign_id' => $campaign->id,
        'promo_campaign_import_id' => $import->id,
        'promo_recipient_id' => $recipient->id,
        'name' => 'Hotel Redirect',
        'email' => 'hotel@example.test',
        'generated_at' => now(),
    ]);

    $this->get(route('promo', ['ref' => $recipient->token]))
        ->assertRedirect(route('hospitality', ['ref' => 'prm_1111111111111111']));
});

it('logt anonieme landing-bezoeken', function () {
    $this->get(route('government'))
        ->assertOk();

    expect(PromoVisit::query()->whereNull('promo_recipient_id')->count())->toBe(1);
    expect(PromoVisit::query()->where('page', PromoVisitPage::Government->value)->count())->toBe(1);

    $this->get(route('government'))
        ->assertOk();

    expect(PromoVisit::query()->whereNull('promo_recipient_id')->count())->toBe(1);
});

it('logt één landing-bezoek per scan-burst voor bestemmeling', function () {
    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_aaaaaaaaaaaaaaaa',
        'label' => 'Burst Test',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $this->get(route('government', ['ref' => $recipient->token]))->assertOk();
    $this->get(route('government', ['ref' => $recipient->token]))->assertOk();
    $this->get(route('government'))->assertOk();

    expect(PromoVisit::query()->where('promo_recipient_id', $recipient->id)->count())->toBe(1);
});

it('toont gemeentenaam in welkomstkader bij landing via ref', function () {
    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_bbbbbbbbbbbbbbbb',
        'label' => 'Aalter',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $this->get(route('government', ['ref' => $recipient->token]))
        ->assertOk()
        ->assertSee(__('landings.shared.recipient_welcome', ['label' => 'Aalter']));
});

it('logt geen landing-bezoek bij bekende mailscanner user-agent', function () {
    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_scanner0000001',
        'label' => 'Scanner Test',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $this->withHeader('User-Agent', 'Proofpoint URL Defense')
        ->get(route('government', ['ref' => $recipient->token]))
        ->assertOk();

    expect(PromoVisit::query()->where('promo_recipient_id', $recipient->id)->count())->toBe(0);
});

it('logt landing-bezoeken per bestemmeling via ref', function () {
    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_0123456789abcdef',
        'label' => 'FC Test',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $this->get(route('government', ['ref' => $recipient->token]))
        ->assertOk();

    $this->travel(3)->minutes();

    $this->get(route('government'))
        ->assertOk();

    expect(PromoVisit::query()->where('promo_recipient_id', $recipient->id)->count())->toBe(2);
    expect(PromoVisit::query()->whereNull('promo_recipient_id')->count())->toBe(0);
});

it('onthoudt ref in sessie na taalwissel op landing', function () {
    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_fedcba9876543210',
        'label' => 'Club B',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $this->get(route('government', ['ref' => $recipient->token]))->assertOk();

    $this->travel(3)->minutes();

    $this->get(route('government'))->assertOk();

    expect(PromoVisit::query()->where('promo_recipient_id', $recipient->id)->count())->toBe(2);
});

it('logt hospitality-hits apart van welcome', function () {
    $this->get(route('hospitality'))->assertOk();

    expect(PromoVisit::query()->where('page', PromoVisitPage::Hospitality->value)->count())->toBe(1)
        ->and(PromoVisit::query()->where('page', PromoVisitPage::Welcome->value)->count())->toBe(0);
});

it('redirect van kale /hospitality naar locale-prefix', function () {
    $this->withHeader('Accept-Language', 'xx-XX,xx;q=0.9')
        ->get('/hospitality')
        ->assertRedirect(route('hospitality', ['locale' => 'nl']));
});

it('logt video-play maximaal een keer per bestemmeling', function () {
    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_aabbccddeeff0011',
        'label' => 'Club Video',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $this->withSession(['promo_recipient_token' => $recipient->token])
        ->postJson(route('promo.track.video'), ['video_key' => 'hospitality'])
        ->assertNoContent();

    $this->withSession(['promo_recipient_token' => $recipient->token])
        ->postJson(route('promo.track.video'), ['video_key' => 'hospitality'])
        ->assertNoContent();

    expect(PromoVideoPlay::query()->where('promo_recipient_id', $recipient->id)->count())->toBe(1);
});

it('weigert video-tracking zonder bestemmeling-sessie', function () {
    $this->postJson(route('promo.track.video'), ['video_key' => 'hospitality'])
        ->assertNotFound();
});

it('toont statistieken bestemmelingen gesorteerd op bezoeken', function () {
    $superuser = User::factory()->superuser()->create();
    $low = PromoRecipient::query()->create([
        'token' => 'prm_cccccccccccccccc',
        'label' => 'Laag',
        'note' => null,
        'created_by' => $superuser->id,
    ]);
    $high = PromoRecipient::query()->create([
        'token' => 'prm_dddddddddddddddd',
        'label' => 'Hoog',
        'note' => null,
        'created_by' => $superuser->id,
    ]);
    PromoRecipient::query()->create([
        'token' => 'prm_eeeeeeeeeeeeeeee',
        'label' => 'Geen bezoeken',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    PromoVisit::query()->create([
        'promo_recipient_id' => $low->id,
        'locale' => 'nl',
        'visited_at' => now(),
    ]);
    PromoVisit::query()->create([
        'promo_recipient_id' => $high->id,
        'locale' => 'nl',
        'visited_at' => now(),
    ]);
    PromoVisit::query()->create([
        'promo_recipient_id' => $high->id,
        'locale' => 'en',
        'visited_at' => now(),
    ]);

    Livewire::actingAs($superuser)
        ->test(PromoRecipients::class)
        ->set('statsOpen', true)
        ->assertSee('Hoog')
        ->assertSee('Laag')
        ->assertDontSee('Geen bezoeken');

    $html = Livewire::actingAs($superuser)
        ->test(PromoRecipients::class)
        ->set('statsOpen', true)
        ->html();

    expect(mb_strpos($html, 'Hoog'))->toBeLessThan(mb_strpos($html, 'Laag'));
});

it('laat superuser promo-bestemmelingen beheren', function () {
    $superuser = User::factory()->superuser()->create();

    $this->actingAs($superuser)
        ->get(route('platform.promo-recipients'))
        ->assertOk()
        ->assertSee(__('platform.promo_recipients.title'));
});

it('toont e-mailstatus per bestemmeling', function () {
    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_bbbbbbbbbbbbbbbb',
        'label' => 'Aalter',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    MunicipalPromoEmailSend::query()->create([
        'campaign' => 'wave-1',
        'promo_recipient_id' => $recipient->id,
        'municipality_name' => 'Aalter',
        'recipient_email' => 'gemeente@aalter.be',
        'docx_filename' => '9880_aalter.docx',
        'status' => MunicipalPromoEmailSendStatus::Sent,
        'sent_at' => now()->setDate(2026, 6, 24)->setTime(14, 30),
        'created_by' => $superuser->id,
    ]);

    Livewire::actingAs($superuser)
        ->test(PromoRecipients::class)
        ->set('listOpen', true)
        ->assertSee(__('platform.promo_recipients.email_sent', [
            'campaign' => 'wave-1',
            'date' => '24-06-2026 14:30',
        ]));
});

it('blokkeert promo-bestemmelingen voor normale gebruikers', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('platform.promo-recipients'))
        ->assertForbidden();
});

it('downloadt promo-bestemmeling QR als PNG', function () {
    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_1122334455667788',
        'label' => 'Club QR',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $this->actingAs($superuser)
        ->get(route('platform.promo-recipients.qr', $recipient))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'attachment; filename="winprox-promo-club-qr.png"');
});

it('pagineert de bestemmelingenlijst en zoekt op naam', function () {
    $superuser = User::factory()->superuser()->create();

    PromoRecipient::query()->create([
        'token' => 'prm_0000000000000000',
        'label' => 'Oudste Bestemmeling',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    foreach (range(1, 25) as $index) {
        PromoRecipient::query()->create([
            'token' => sprintf('prm_%016d', $index),
            'label' => 'Vulling '.$index,
            'note' => null,
            'created_by' => $superuser->id,
        ]);
    }

    Livewire::actingAs($superuser)
        ->test(PromoRecipients::class)
        ->set('listOpen', true)
        ->assertSee('Vulling 25')
        ->assertDontSee('Oudste Bestemmeling')
        ->set('search', 'Oudste')
        ->assertSee('Oudste Bestemmeling')
        ->assertDontSee('Vulling 25');
});

it('maakt bestemmeling aan via action met audit', function () {
    $superuser = User::factory()->superuser()->create();

    $recipient = app(\App\Actions\Marketing\CreatePromoRecipientAction::class)->handle(
        label: 'Nieuwe club',
        note: 'via test',
        actorUserId: (int) $superuser->id,
    );

    expect($recipient->token)->toStartWith('prm_')
        ->and($recipient->label)->toBe('Nieuwe club');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'marketing.promo_recipient_created',
        'user_id' => $superuser->id,
    ]);
});
