<?php

use App\Actions\Marketing\CreatePromoCampaignAction;
use App\Actions\Marketing\ResolvePromoRecipientLandingAction;
use App\Enums\PromoLanding;
use App\Models\PromoCampaignImport;
use App\Models\PromoCampaignTarget;
use App\Models\PromoRecipient;
use App\Models\User;

it('kiest government zonder bestemmeling of campagne', function () {
    expect(app(ResolvePromoRecipientLandingAction::class)->handle(null))
        ->toBe(PromoLanding::Government);

    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_nolanding000001',
        'label' => 'Zonder campagne',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    expect(app(ResolvePromoRecipientLandingAction::class)->handle($recipient))
        ->toBe(PromoLanding::Government);
});

it('kiest de landing van de laatste campagne van de bestemmeling', function () {
    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_haslanding00001',
        'label' => 'Met campagne',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $older = app(CreatePromoCampaignAction::class)->handle(
        slug: 'older-government',
        name: 'Ouder',
        locale: 'nl',
        actorUserId: (int) $superuser->id,
        landing: PromoLanding::Government,
    );
    $newer = app(CreatePromoCampaignAction::class)->handle(
        slug: 'newer-hospitality',
        name: 'Nieuwer',
        locale: 'nl',
        actorUserId: (int) $superuser->id,
        landing: PromoLanding::Hospitality,
    );

    foreach ([$older, $newer] as $campaign) {
        $import = PromoCampaignImport::query()->create([
            'promo_campaign_id' => $campaign->id,
            'original_filename' => 'one.xlsx',
            'row_count' => 1,
            'imported_by' => $superuser->id,
            'imported_at' => now(),
        ]);
        PromoCampaignTarget::query()->create([
            'promo_campaign_id' => $campaign->id,
            'promo_campaign_import_id' => $import->id,
            'promo_recipient_id' => $recipient->id,
            'name' => 'Met campagne',
            'email' => 'camp@example.test',
            'generated_at' => now(),
        ]);
    }

    expect(app(ResolvePromoRecipientLandingAction::class)->handle($recipient))
        ->toBe(PromoLanding::Hospitality);
});
