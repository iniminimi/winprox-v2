<?php

use App\Actions\Marketing\RecordPromoVisitAction;
use App\Enums\PromoVisitPage;
use App\Models\PromoRecipient;
use App\Models\PromoVisit;
use App\Models\User;
use Illuminate\Support\Carbon;

it('logt promo- en welcome-bezoeken apart voor dezelfde bestemmeling', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-11 12:00:00', config('app.timezone')));

    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_abcdef0123456789',
        'label' => 'Amay',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $action = app(RecordPromoVisitAction::class);

    expect($action->handle($recipient->id, 'nl', now(), PromoVisitPage::Promo))->not->toBeNull()
        ->and($action->handle($recipient->id, 'nl', now(), PromoVisitPage::Welcome))->not->toBeNull()
        ->and(PromoVisit::query()->where('promo_recipient_id', $recipient->id)->count())->toBe(2)
        ->and(PromoVisit::query()->where('page', PromoVisitPage::Welcome->value)->count())->toBe(1);

    // Dedupe binnen 2 minuten per pagina.
    expect($action->handle($recipient->id, 'nl', now()->addMinute(), PromoVisitPage::Welcome))->toBeNull()
        ->and(PromoVisit::query()->where('page', PromoVisitPage::Welcome->value)->count())->toBe(1);

    Carbon::setTestNow();
});
