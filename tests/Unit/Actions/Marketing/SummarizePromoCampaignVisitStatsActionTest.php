<?php

use App\Actions\Marketing\CreatePromoCampaignAction;
use App\Actions\Marketing\ImportPromoCampaignSpreadsheetAction;
use App\Actions\Marketing\RecordPromoVisitAction;
use App\Actions\Marketing\SummarizePromoCampaignVisitStatsAction;
use App\Enums\PromoVisitPage;
use App\Models\PromoCampaignTarget;
use App\Models\PromoRecipient;
use App\Models\User;
use Illuminate\Support\Carbon;

function promoVisitStatsFixturePath(): string
{
    return base_path('tests/fixtures/promo_campaign_sample.xlsx');
}

it('sommeert welcome- en promo-kliks per campagne-ontvanger', function () {
    $path = promoVisitStatsFixturePath();
    if (! is_file($path)) {
        $this->markTestSkipped('Promo campaign fixture ontbreekt.');
    }

    Carbon::setTestNow(Carbon::parse('2026-08-11 12:00:00', config('app.timezone')));

    $superuser = User::factory()->superuser()->create();

    $campaign = app(CreatePromoCampaignAction::class)->handle(
        slug: 'visit-stats-test',
        name: 'Visit stats test',
        locale: 'nl',
        actorUserId: (int) $superuser->id,
    );

    app(ImportPromoCampaignSpreadsheetAction::class)->handle(
        campaign: $campaign,
        spreadsheetPath: $path,
        originalFilename: 'sample.xlsx',
        columnMapping: [
            'name' => 'naam',
            'email' => 'e-mail',
            'street_address' => 'adres',
            'postal_code' => 'postcode',
        ],
        actorUserId: (int) $superuser->id,
    );

    $target = PromoCampaignTarget::query()
        ->where('promo_campaign_id', $campaign->id)
        ->orderBy('id')
        ->firstOrFail();

    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_visitstats0123456789',
        'label' => $target->name,
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $target->update(['promo_recipient_id' => $recipient->id]);

    $record = app(RecordPromoVisitAction::class);
    $record->handle($recipient->id, 'nl', now(), PromoVisitPage::Welcome);
    $record->handle($recipient->id, 'nl', now()->addMinutes(5), PromoVisitPage::Welcome);
    $record->handle($recipient->id, 'nl', now()->addMinutes(5), PromoVisitPage::Promo);

    $stats = app(SummarizePromoCampaignVisitStatsAction::class)->handle($campaign->fresh());

    expect($stats->welcome)->toBe(2)
        ->and($stats->promo)->toBe(1)
        ->and($stats->targetsWithVisits)->toBe(1)
        ->and($stats->forTarget((int) $target->id))->toBe(['welcome' => 2, 'promo' => 1]);

    Carbon::setTestNow();
});
