<?php

use App\Actions\Marketing\CreatePromoCampaignAction;
use App\Enums\PromoCampaignDeliveryStatus;
use App\Enums\PromoLanding;
use App\Models\PromoCampaign;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('weigert promo-campagne API voor normale gebruikers', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['*'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/promo-campaigns')
        ->assertForbidden();
});

it('lijst promo-campagnes op voor superuser via API', function () {
    $superuser = User::factory()->superuser()->create();
    app(CreatePromoCampaignAction::class)->handle(
        slug: 'api-list',
        name: 'API lijst',
        locale: 'nl',
        actorUserId: (int) $superuser->id,
        landing: PromoLanding::Government,
    );

    $token = $superuser->createToken('platform', ['*'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/promo-campaigns')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'api-list')
        ->assertJsonStructure([
            'data' => [
                ['id', 'slug', 'name', 'locale', 'landing', 'delivery'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

it('maakt en werkt promo-campagne bij via API', function () {
    $superuser = User::factory()->superuser()->create();
    $token = $superuser->createToken('platform', ['*'])->plainTextToken;

    $create = $this->withToken($token)
        ->postJson('/api/v1/promo-campaigns', [
            'slug' => 'api-create',
            'name' => 'API create',
            'locale' => 'nl',
            'landing' => PromoLanding::Government->value,
        ]);

    $create->assertCreated()
        ->assertJsonPath('data.slug', 'api-create');

    $campaignId = (int) $create->json('data.id');

    $this->withToken($token)
        ->patchJson('/api/v1/promo-campaigns/'.$campaignId, [
            'name' => 'API updated',
            'locale' => 'nl',
            'landing' => PromoLanding::Government->value,
            'email_subject' => 'Onderwerp',
            'email_body_html' => '<p>Body</p>',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'API updated')
        ->assertJsonPath('data.email_subject', 'Onderwerp');

    $this->withToken($token)
        ->getJson('/api/v1/promo-campaigns/'.$campaignId)
        ->assertOk()
        ->assertJsonPath('data.email_body_html', '<p>Body</p>')
        ->assertJsonPath('data.delivery.status', PromoCampaignDeliveryStatus::NoRecipients->value);
});

it('zet promo-mails in de wachtrij en onderbreekt via API', function () {
    Queue::fake();

    $superuser = User::factory()->superuser()->create();
    [$campaign] = promoCampaignReadyForEmail($superuser, 'api-queue@example.com');
    $token = $superuser->createToken('platform', ['*'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/promo-campaigns/'.$campaign->id.'/queue-emails', [
            'delay_seconds' => 20,
        ])
        ->assertOk()
        ->assertJsonPath('data.queued', 1);

    $this->withToken($token)
        ->postJson('/api/v1/promo-campaigns/'.$campaign->id.'/pause-sending')
        ->assertOk()
        ->assertJsonPath('data.paused_campaigns', 1);

    expect($campaign->fresh()->isEmailSendingPaused())->toBeTrue();

    $this->withToken($token)
        ->postJson('/api/v1/promo-campaigns/'.$campaign->id.'/resume-sending')
        ->assertOk()
        ->assertJsonPath('data.resumed_campaigns', 1);

    expect($campaign->fresh()->isEmailSendingPaused())->toBeFalse();
});

it('verwijdert promo-campagne via API', function () {
    $superuser = User::factory()->superuser()->create();
    $campaign = app(CreatePromoCampaignAction::class)->handle(
        slug: 'api-delete',
        name: 'API delete',
        locale: 'nl',
        actorUserId: (int) $superuser->id,
        landing: PromoLanding::Government,
    );
    $token = $superuser->createToken('platform', ['*'])->plainTextToken;

    $this->withToken($token)
        ->deleteJson('/api/v1/promo-campaigns/'.$campaign->id)
        ->assertNoContent();

    expect(PromoCampaign::query()->find($campaign->id))->toBeNull();
});
