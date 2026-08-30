<?php

use App\Actions\Marketing\CreatePromoCampaignAction;
use App\Actions\Marketing\SortPromoCampaignsForPlatformListAction;
use App\Actions\Marketing\SummarizePromoCampaignsDeliveryAction;
use App\Enums\PromoCampaignDeliveryStatus;
use App\Enums\PromoLanding;
use App\Jobs\SendPromoCampaignEmailJob;
use App\Livewire\Platform\PromoCampaigns;
use App\Models\PromoCampaign;
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
