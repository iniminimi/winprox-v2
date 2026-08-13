<?php

use App\Actions\Marketing\RecordWelcomeVisitAction;
use App\Actions\Marketing\SummarizeWelcomeVisitsAction;
use App\Models\User;
use App\Models\WelcomeVisit;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('logt unieke welcome-bezoekers max eens per dag', function () {
    $action = app(RecordWelcomeVisitAction::class);

    expect($action->handle('nl', '1.2.3.4', 'Mozilla/5.0'))->not->toBeNull()
        ->and($action->handle('nl', '1.2.3.4', 'Mozilla/5.0'))->toBeNull()
        ->and(WelcomeVisit::query()->count())->toBe(1);

    expect($action->handle('fr', '1.2.3.4', 'Mozilla/5.0'))->toBeNull()
        ->and($action->handle('nl', '9.9.9.9', 'Mozilla/5.0'))->not->toBeNull()
        ->and(WelcomeVisit::query()->count())->toBe(2);
});

it('slaat geautomatiseerde welcome-hits over', function () {
    expect(app(RecordWelcomeVisitAction::class)->handle('nl', '1.2.3.4', 'curl/8.0'))->toBeNull()
        ->and(WelcomeVisit::query()->count())->toBe(0);
});

it('toont unieke welcome-statistieken op platformdashboard', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-18 12:00:00', config('app.timezone')));

    WelcomeVisit::query()->create([
        'visited_at' => now(),
        'locale' => 'nl',
        'visitor_hash' => hash_hmac('sha256', 'a', (string) config('app.key')),
        'utm_source' => null,
        'utm_medium' => null,
        'utm_campaign' => null,
    ]);
    WelcomeVisit::query()->create([
        'visited_at' => now()->subDays(3),
        'locale' => 'fr',
        'visitor_hash' => hash_hmac('sha256', 'b', (string) config('app.key')),
        'utm_source' => null,
        'utm_medium' => null,
        'utm_campaign' => null,
    ]);

    $stats = app(SummarizeWelcomeVisitsAction::class)->handle();

    expect($stats->uniqueToday)->toBe(1)
        ->and($stats->uniqueLast7Days)->toBe(2)
        ->and($stats->uniqueYear2026)->toBe(2)
        ->and($stats->byLocale['nl'])->toBe(1)
        ->and($stats->byLocale['fr'])->toBe(1);

    $superuser = User::factory()->superuser()->create();

    Livewire::actingAs($superuser)
        ->test(\App\Livewire\Platform\Dashboard::class)
        ->assertSee(__('platform.dashboard.welcome_unique_title'))
        ->assertSee(__('platform.dashboard.welcome_locale_title'));

    Carbon::setTestNow();
});

it('logt welcome-bezoek via de publieke route', function () {
    $html = $this->get('/nl/?utm_source=promo&utm_campaign=wave-1')
        ->assertOk()
        ->assertSee('wp-welcome-nav-group', false)
        ->assertSee(__('welcome.nav.group_products'), false)
        ->assertSee(__('welcome.nav.group_how'), false)
        ->assertSee(__('welcome.nav.group_more'), false)
        ->assertSee('id="iot"', false)
        ->assertSee(__('welcome.iot.eyebrow'), false)
        ->assertSee(__('welcome.iot.body'), false)
        ->assertSee('images/welcome/IoT.jpg', false)
        ->getContent();

    expect($html)->toContain('video/nl/hospitality_NL.mp4')
        ->and($html)->toMatch('/wp-welcome-hero-qr-video[\s\S]*?\bautoplay\b[\s\S]*?hospitality_NL\.mp4/');

    expect(WelcomeVisit::query()->count())->toBe(1)
        ->and(WelcomeVisit::query()->first()?->utm_source)->toBe('promo')
        ->and(WelcomeVisit::query()->first()?->utm_campaign)->toBe('wave-1')
        ->and(WelcomeVisit::query()->first()?->locale)->toBe('nl');
});

it('koppelt een welcome-bezoek aan een promo-bestemmeling via ref', function () {
    $superuser = User::factory()->superuser()->create();
    $recipient = \App\Models\PromoRecipient::query()->create([
        'token' => 'prm_fedcba9876543210',
        'label' => 'Amay',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $this->get('/nl/?ref='.$recipient->token)
        ->assertOk();

    expect(\App\Models\PromoVisit::query()->where('promo_recipient_id', $recipient->id)->count())->toBe(1)
        ->and(\App\Models\PromoVisit::query()->where('promo_recipient_id', $recipient->id)->value('page'))
        ->toBe(\App\Enums\PromoVisitPage::Welcome)
        ->and(WelcomeVisit::query()->count())->toBe(1);
});

it('toont welcome_url uitleg op de promo-campagnepagina', function () {
    $superuser = User::factory()->superuser()->create();
    $campaign = \App\Models\PromoCampaign::query()->create([
        'slug' => 'welcome-url-demo',
        'name' => 'Welcome URL demo',
        'locale' => 'nl',
        'letter_body_html' => '<p>Brief</p>',
        'email_subject' => 'Hallo',
        'email_body_html' => '<p><a href="{{welcome_url}}">Bekijk WinProx</a></p>',
        'attach_letter_to_email' => false,
        'created_by' => $superuser->id,
    ]);

    Livewire::actingAs($superuser)
        ->test(\App\Livewire\Platform\PromoCampaignEdit::class, ['promoCampaign' => $campaign])
        ->assertSee(__('platform.promo_campaigns.welcome_url_how_to_title'))
        ->assertSee(__('platform.promo_campaigns.welcome_url_how_to'), false)
        ->assertSee('{{welcome_url}}', false);
});
