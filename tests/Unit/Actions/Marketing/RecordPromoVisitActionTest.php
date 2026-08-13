<?php

use App\Actions\Marketing\RecordPromoVisitAction;
use App\Enums\PromoVisitFollowKey;
use App\Enums\PromoVisitKind;
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

    expect($action->handle($recipient->id, 'nl', now()->addMinute(), PromoVisitPage::Welcome))->toBeNull()
        ->and(PromoVisit::query()->where('page', PromoVisitPage::Welcome->value)->count())->toBe(1);

    Carbon::setTestNow();
});

it('logt bevestigd bezoek los van de link-hit', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-13 10:00:00', config('app.timezone')));

    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_engagedvisit0001',
        'label' => 'Hotel Test',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $action = app(RecordPromoVisitAction::class);
    $action->handle($recipient->id, 'nl', now(), PromoVisitPage::Welcome);
    $action->handle(
        $recipient->id,
        'nl',
        now(),
        PromoVisitPage::Welcome,
        PromoVisitKind::Engaged,
    );

    expect(PromoVisit::query()->where('promo_recipient_id', $recipient->id)->count())->toBe(2)
        ->and(PromoVisit::query()->where('kind', PromoVisitKind::Engaged->value)->count())->toBe(1);

    expect($action->handle(
        $recipient->id,
        'nl',
        now()->addMinute(),
        PromoVisitPage::Welcome,
        PromoVisitKind::Engaged,
    ))->toBeNull();

    Carbon::setTestNow();
});

it('logt doorklik een keer per bestemmeling en pagina', function () {
    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_followvisit00001',
        'label' => 'Hotel Follow',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $action = app(RecordPromoVisitAction::class);

    expect($action->handle(
        $recipient->id,
        'nl',
        now(),
        PromoVisitPage::Welcome,
        PromoVisitKind::Follow,
        PromoVisitFollowKey::Contact,
    ))->not->toBeNull();

    expect($action->handle(
        $recipient->id,
        'nl',
        now(),
        PromoVisitPage::Welcome,
        PromoVisitKind::Follow,
        PromoVisitFollowKey::Contact,
    ))->toBeNull();

    expect($action->handle(
        $recipient->id,
        'nl',
        now(),
        PromoVisitPage::Welcome,
        PromoVisitKind::Follow,
        PromoVisitFollowKey::Register,
    ))->not->toBeNull();

    expect(PromoVisit::query()->where('kind', PromoVisitKind::Follow->value)->count())->toBe(2);
});
