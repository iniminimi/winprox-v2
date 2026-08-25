<?php

use App\Livewire\Auth\Register;
use App\Livewire\Pages\Subscription;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Support\RegisterFormData;

afterEach(fn () => Tenancy::forget());

it('toont de welcome-pagina voor gasten', function () {
    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee(__('welcome.hero.badge'))
        ->assertSee(__('welcome.trust_bar.items.0'))
        ->assertSee(__('welcome.products.time.name'))
        ->assertSee(__('welcome.products.time.tagline'))
        ->assertDontSee('id="faq-how_it_works"', false)
        ->assertSee('property="og:description" content="'.__('welcome.social.og_description').'"', false)
        ->assertSee('/images/promo/og_1.jpg', false)
        ->assertSee('images/welcome/1995/easter_egg.gif', false)
        ->assertSee(route('welcome.classic'), false)
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"SoftwareApplication"', false);
});

it('serveert llms.txt voor AI-bots', function () {
    $this->get(route('llms.txt'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSee('# WinProx', false)
        ->assertSee('> Facility management via QR portals', false)
        ->assertSee(route('faq.public', ['locale' => 'en'], absolute: true), false)
        ->assertSee(__('faq.items.how_it_works.title', [], 'en'), false);
});

it('toont de publieke FAQ-pagina met alle antwoorden zichtbaar', function () {
    $this->get(route('faq.public', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee(__('faq.title'))
        ->assertSee(__('faq.items.how_it_works.title'))
        ->assertSee(__('faq.items.how_it_works.intro'))
        ->assertSee(__('faq.items.pricing.title'))
        ->assertSee('id="faq-how_it_works"', false);
});

it('toont de 1995 easter-egg pagina met noindex', function () {
    $this->get(route('welcome.classic'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('noindex, nofollow', false)
        ->assertSee('Gastenboek', false)
        ->assertSee(route('register'), false)
        ->assertSee(route('welcome'), false);
});

it('zet open graph meta op een sectorlanding', function () {
    $this->get(route('government', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('property="og:title" content="'.__('landings.government.social.og_title', [], 'en').'"', false)
        ->assertSee('property="og:description" content="'.__('landings.government.social.og_description', [], 'en').'"', false)
        ->assertSee('/images/promo/og_1.jpg', false);
});

it('toont hospitality-video wanneer het bestand bestaat', function () {
    $video = \App\Support\Marketing\SectorLandingVideo::relativePath(
        \App\Enums\PromoLanding::Hospitality,
        'nl',
    );
    if ($video === null) {
        $this->markTestSkipped('Hospitality-video ontbreekt.');
    }

    $this->get(route('hospitality', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee(__('landings.hospitality.title', [], 'nl'))
        ->assertSee(__('landings.hospitality.video.title', [], 'nl'))
        ->assertSee(basename($video), false);
});

it('toont de uitgebreide industry-landing met NL-video of placeholder', function () {
    $this->get(route('industry', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee(__('landings.industry.title', [], 'nl'))
        ->assertSee(__('landings.industry.flow', [], 'nl'))
        ->assertSee(__('landings.industry.problem.title', [], 'nl'))
        ->assertSee(__('landings.industry.start.trial', [], 'nl'))
        ->assertSee('images/landing/industry/image_01.jpg', false)
        ->assertSee('images/landing/industry/image_06.jpg', false)
        ->assertSee('wp-landing-block--wide-photo', false)
        ->assertSee('wp-landing-close--scrim', false)
        ->assertSee('industry_promo_nl.mp4', false)
        ->assertSee('wp-video--sm', false)
        ->assertDontSee('Bekijk demo', false);

    $this->get(route('industry', ['locale' => 'fr']))
        ->assertOk()
        ->assertSee(__('landings.industry.title', [], 'fr'))
        ->assertSee(__('landings.shared.video_placeholder', [], 'fr'))
        ->assertSee('id="landing-video"', false);
});

it('toont de uitgebreide hospitality-landing zonder demo of prijs', function () {
    $this->get(route('hospitality', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee(__('landings.hospitality.title', [], 'nl'))
        ->assertSee(__('landings.hospitality.flow', [], 'nl'))
        ->assertSee(__('landings.hospitality.problem.title', [], 'nl'))
        ->assertSee(__('landings.hospitality.roles.items.3.title', [], 'nl'))
        ->assertSee(__('landings.hospitality.start.trial', [], 'nl'))
        ->assertSee('images/landing/hospitality/image_03.jpg', false)
        ->assertSee('images/landing/hospitality/image_06.jpg', false)
        ->assertSee('wp-landing-block--wide-photo', false)
        ->assertSee('wp-landing-close--scrim', false)
        ->assertSee('wp-video--sm', false)
        ->assertSee('id="landing-video"', false)
        ->assertDontSee('Bekijk demo', false)
        ->assertDontSee('langdurig contract', false)
        ->assertDontSee('START GRATIS', false)
        ->assertDontSee('images/landing/general/welcome_01.jpg', false);

    $this->get(route('hospitality', ['locale' => 'fr']))
        ->assertOk()
        ->assertSee(__('landings.hospitality.title', [], 'fr'))
        ->assertSee(__('landings.hospitality.problem.title', [], 'fr'))
        ->assertSee('id="landing-video"', false)
        ->assertDontSee('démo', false);
});

it('toont de uitgebreide healthcare- en government-landing', function () {
    $this->get(route('healthcare', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee(__('landings.healthcare.title', [], 'nl'))
        ->assertSee(__('landings.healthcare.flow', [], 'nl'))
        ->assertSee(__('landings.healthcare.problem.title', [], 'nl'))
        ->assertSee(__('landings.healthcare.roles.items.3.title', [], 'nl'))
        ->assertSee(__('landings.healthcare.start.trial', [], 'nl'))
        ->assertSee('images/landing/healthcare/05.jpg', false)
        ->assertSee('images/landing/healthcare/image_03.jpg', false)
        ->assertSee('wp-landing-block--wide-photo', false)
        ->assertSee('wp-landing-close--scrim', false)
        ->assertSee('id="landing-video"', false)
        ->assertSee(__('landings.shared.video_placeholder', [], 'nl'))
        ->assertDontSee('Bekijk demo', false)
        ->assertDontSee('langdurig contract', false);

    $this->get(route('government', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee(__('landings.government.title', [], 'nl'))
        ->assertSee(__('landings.government.flow', [], 'nl'))
        ->assertSee(__('landings.government.problem.title', [], 'nl'))
        ->assertSee(__('landings.government.roles.items.0.title', [], 'nl'))
        ->assertSee('images/landing/gouvernment/image_01.jpg', false)
        ->assertSee('images/landing/gouvernment/image_05.jpg', false)
        ->assertSee('wp-landing-block--wide-photo', false)
        ->assertSee('wp-landing-close--scrim', false)
        ->assertSee('wp-landing-close__photo--lower', false)
        ->assertSee('id="landing-video"', false)
        ->assertSee('issue_nl_01.mp4', false)
        ->assertSee('wp-video--sm', false)
        ->assertSee(__('landings.shared.cta_video', [], 'nl'))
        ->assertDontSee('Bekijk demo', false);

    $this->get(route('healthcare', ['locale' => 'fr']))
        ->assertOk()
        ->assertSee(__('landings.healthcare.problem.title', [], 'fr'))
        ->assertSee('id="landing-video"', false);
});

it('toont de uitgebreide realestate-landing', function () {
    $this->get(route('realestate', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee(__('landings.realestate.title', [], 'nl'))
        ->assertSee(__('landings.realestate.flow', [], 'nl'))
        ->assertSee(__('landings.realestate.problem.title', [], 'nl'))
        ->assertSee(__('landings.realestate.roles.items.0.title', [], 'nl'))
        ->assertSee(__('landings.realestate.start.trial', [], 'nl'))
        ->assertSee('id="landing-video"', false)
        ->assertSee(__('landings.shared.video_placeholder', [], 'nl'))
        ->assertSee('images/landing/general/welcome_01.jpg', false)
        ->assertSee('images/landing/general/welcome_07.jpg', false)
        ->assertSee('wp-landing-close--overlay', false)
        ->assertDontSee('Bekijk demo', false)
        ->assertDontSee('langdurig contract', false);

    $this->get('/realestate')
        ->assertRedirect(route('realestate', ['locale' => 'nl']));
});

it('toont taalkeuze bovenaan een sectorlanding', function () {
    $this->get(route('government'))
        ->assertOk()
        ->assertSee(__('common.language.label'), false)
        ->assertSee('wp-welcome-nav', false)
        ->assertDontSee('wp-promo-body', false);
});

it('zet een proefperiode bij registratie', function () {
    Livewire::test(Register::class)
        ->set(array_replace(RegisterFormData::valid(), [
            'organization' => 'Trial Facility BV',
            'name' => 'Trial Admin',
            'email' => 'trial@winprox.test',
        ]))
        ->call('register')
        ->assertHasNoErrors();

    $tenant = Tenant::where('name', 'Trial Facility BV')->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->trial_ends_at)->not->toBeNull()
        ->and($tenant->isTrialActive())->toBeTrue();
});

it('stuurt gebruikers zonder toegang door naar abonnement', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->subDay(),
        'billing_plan' => null,
        'billing_active_until' => null,
        'is_active' => true,
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('subscription.index'));
});

it('toont WinProx-jaarformules en Corporate op abonnement', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->addDays(5),
        'has_esg_module' => false,
        'has_time_module' => false,
        'is_active' => true,
    ]);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    Livewire::actingAs($admin)
        ->test(Subscription::class)
        ->assertSee(__('subscription.plans.winprox_10.name'))
        ->assertSee(__('subscription.plans.winprox_100.name'))
        ->assertSee(__('subscription.plans.corporate.name'))
        ->assertSee(__('subscription.time_addon.label'))
        ->assertDontSee(__('subscription.plans.facility_25.name'));
});

it('toont planlabel correct bij billing_plan met hoofdletter', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => null,
        'billing_plan' => 'Facility_100',
        'billing_active_until' => now()->addMonth(),
        'is_active' => true,
    ]);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    Livewire::actingAs($admin)
        ->test(Subscription::class)
        ->assertSee(__('subscription.plans.facility_100.name'))
        ->assertDontSee('subscription.plans.Facility_100.name');

    expect($tenant->effectivePlanKey())->toBe('facility_100')
        ->and($tenant->hasCsvUnitsImport())->toBeTrue();
});

it('toont csv-import knop op locatie-detail bij facility_100-plan', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => null,
        'billing_plan' => 'facility_100',
        'billing_active_until' => now()->addMonth(),
        'is_active' => true,
    ]);
    Tenancy::actAs($tenant->id);
    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    expect($tenant->hasCsvUnitsImport())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('locations.show', $location))
        ->assertOk()
        ->assertSee(__('locations.units_csv.button'), false);
});

it('laat een beheerder een plan activeren', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->addDays(3),
        'is_active' => true,
    ]);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    Livewire::actingAs($admin)
        ->test(Subscription::class)
        ->call('activatePlan', 'winprox_100')
        ->assertHasNoErrors();

    $tenant->refresh();

    expect($tenant->billing_plan)->toBe('winprox_100')
        ->and($tenant->billing_active_until)->not->toBeNull()
        ->and($tenant->isPaidSubscriptionActive())->toBeTrue()
        ->and($tenant->isTrialActive())->toBeFalse()
        ->and($tenant->hasTimeModule())->toBeFalse()
        ->and($tenant->hasEsgModule())->toBeFalse();
});

it('laadt de FAQ-pagina met facility-inhoud', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('faq.index'))
        ->assertOk()
        ->assertSee(__('faq.title'))
        ->assertSee(__('faq.items.how_it_works.title'))
        ->assertSee(__('faq.items.qr_code.title'))
        ->assertSee(__('faq.items.pricing.title'))
        ->assertSee(__('faq.items.moderation.title'))
        ->assertSee(__('faq.items.time_clock.title'))
        ->assertSee(__('faq.items.internal_teams.title'), false)
        ->assertSee(__('faq.items.microsoft_login.title'), false);
});

it('toont privacy-document publiek', function () {
    $this->get(route('legal.privacy', ['locale' => 'en']))
        ->assertOk()
        ->assertSee(__('legal.documents.privacy', [], 'en'))
        ->assertSee(__('legal.applicable_law_notice', [], 'en'))
        ->assertSee('QR reports', false)
        ->assertSee('ESG & Compliance', false)
        ->assertSee('Sign in with Microsoft', false);
});

it('toont contact voor gasten', function () {
    $this->get(route('contact.index'))
        ->assertOk()
        ->assertSee('info@winprox.app')
        ->assertSee(__('contact.title'))
        ->assertSee('video/assistant.mp4', false)
        ->assertSee('wp-contact-assistant__video', false);
});

it('toont de assistant_legal-clip in de juridische-documentenpaginakop', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('legal.index'))
        ->assertOk()
        ->assertSee('video/assistant_legal_80.mp4', false)
        ->assertSee('wp-page-icon--assistant', false);
});
