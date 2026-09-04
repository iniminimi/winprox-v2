<?php

use App\Actions\Marketing\CreatePromoCampaignAction;
use App\Actions\Marketing\SortPromoCampaignsForPlatformListAction;
use App\Actions\Marketing\SummarizePromoCampaignsDeliveryAction;
use App\Enums\MunicipalPromoEmailSendStatus;
use App\Enums\PromoCampaignDeliveryStatus;
use App\Enums\PromoLanding;
use App\Jobs\SendPromoCampaignEmailJob;
use App\Livewire\Platform\PromoCampaigns;
use App\Models\PromoCampaign;
use App\Models\PromoCampaignEmailSend;
use App\Models\PromoCampaignImport;
use App\Models\PromoCampaignTarget;
use App\Models\User;
use Livewire\Livewire;

it('zet campagnes met status sending bovenaan in de platformlijst', function () {
    config(['queue.default' => 'database']);

    $superuser = User::factory()->superuser()->create();
    $older = app(CreatePromoCampaignAction::class)->handle(
        slug: 'older-campaign',
        name: 'Oudere campagne',
        locale: 'nl',
        actorUserId: (int) $superuser->id,
        landing: PromoLanding::Government,
    );
    [$sending] = promoCampaignReadyForEmail($superuser, 'sending-first@example.com');

    SendPromoCampaignEmailJob::dispatch(
        promoCampaignId: (int) $sending->id,
        promoCampaignTargetId: 999,
        actorUserId: (int) $superuser->id,
    )->delay(now()->addHour());

    $campaigns = PromoCampaign::query()->latest('id')->get();
    $summaries = app(SummarizePromoCampaignsDeliveryAction::class)->handle($campaigns);
    $sorted = app(SortPromoCampaignsForPlatformListAction::class)->handle($campaigns, $summaries);

    expect($summaries[$sending->id]->status)->toBe(PromoCampaignDeliveryStatus::Sending)
        ->and($sorted->first()?->id)->toBe($sending->id)
        ->and($sorted->last()?->id)->toBe($older->id);

    Livewire::actingAs($superuser)
        ->test(PromoCampaigns::class)
        ->assertSeeInOrder([$sending->name, $older->name]);

    \Illuminate\Support\Facades\DB::table('jobs')->where('payload', 'like', '%SendPromoCampaignEmailJob%')->delete();
});

it('zet recent verstuurde campagnes boven nieuwer aangemaakte zonder verzending', function () {
    $superuser = User::factory()->superuser()->create();

    $recentlySent = app(CreatePromoCampaignAction::class)->handle(
        slug: 'recently-sent',
        name: 'Net afgerond',
        locale: 'nl',
        actorUserId: (int) $superuser->id,
        landing: PromoLanding::Government,
    );
    $newerNeverSent = app(CreatePromoCampaignAction::class)->handle(
        slug: 'newer-never-sent',
        name: 'Nog niet gestart',
        locale: 'nl',
        actorUserId: (int) $superuser->id,
        landing: PromoLanding::Government,
    );

    $import = PromoCampaignImport::query()->create([
        'promo_campaign_id' => $recentlySent->id,
        'original_filename' => 'test.xlsx',
        'row_count' => 1,
        'imported_by' => $superuser->id,
        'imported_at' => now(),
    ]);
    $target = PromoCampaignTarget::query()->create([
        'promo_campaign_id' => $recentlySent->id,
        'promo_campaign_import_id' => $import->id,
        'name' => 'Ziekenhuis',
        'email' => 'ziekenhuis@example.com',
        'street_address' => 'Straat 1',
        'postal_code' => '1000',
        'city' => 'Brussel',
    ]);
    PromoCampaignEmailSend::query()->create([
        'promo_campaign_id' => $recentlySent->id,
        'promo_campaign_target_id' => $target->id,
        'recipient_email' => 'ziekenhuis@example.com',
        'status' => MunicipalPromoEmailSendStatus::Sent,
        'sent_at' => now(),
        'created_by' => $superuser->id,
    ]);

    $campaigns = PromoCampaign::query()->whereIn('id', [$recentlySent->id, $newerNeverSent->id])->get();
    $summaries = app(SummarizePromoCampaignsDeliveryAction::class)->handle($campaigns);
    $sorted = app(SortPromoCampaignsForPlatformListAction::class)->handle($campaigns, $summaries);

    expect($summaries[$recentlySent->id]->lastSentAt)->not->toBeNull()
        ->and($sorted->pluck('id')->all())->toBe([(int) $recentlySent->id, (int) $newerNeverSent->id]);

    Livewire::actingAs($superuser)
        ->test(PromoCampaigns::class)
        ->assertSeeInOrder([$recentlySent->name, $newerNeverSent->name]);
});
