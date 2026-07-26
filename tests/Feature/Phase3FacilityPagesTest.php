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
        ->assertSee(__('welcome.faq.eyebrow'))
        ->assertSee(__('faq.items.how_it_works.title'))
        ->assertSee(__('faq.items.pricing.title'))
        ->assertSee(__('faq.items.how_it_works.intro'))
        ->assertSee('id="faq"', false)
        ->assertSee('id="faq-how_it_works"', false)
        ->assertSee('property="og:description" content="'.__('welcome.social.og_description').'"', false)
        ->assertSee('/images/promo/og_1.jpg', false)
        ->assertSee('images/welcome/1995/easter_egg.gif', false)
        ->assertSee(route('welcome.classic'), false);
});

it('serveert llms.txt voor AI-bots', function () {
    $this->get(route('llms.txt'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSee('# WinProx', false)
        ->assertSee('> Facility management via QR portals', false)
        ->assertSee(route('welcome', ['locale' => 'en'], absolute: true).'#faq', false)
        ->assertSee(__('faq.items.how_it_works.title', [], 'en'), false);
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

it('zet open graph meta op de promo-pagina', function () {
    $this->get(route('promo', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('property="og:title" content="'.__('promo.social.og_title', [], 'en').'"', false)
        ->assertSee('property="og:description" content="'.__('promo.social.og_description', [], 'en').'"', false)
        ->assertSee('/images/promo/og_1.jpg', false);
});

it('toont beschikbare promo-video per locale', function () {
    $this->withSession(['locale' => 'nl'])
        ->get(route('promo'))
        ->assertOk()
        ->assertSee(__('promo.video.title', [], 'nl'))
        ->assertSee(__('promo.video.beheerportaal.title', [], 'nl'))
        ->assertSee(__('promo.video.beheerportaal.items.0.description', [], 'nl'))
        ->assertSee('issue_nl_01.mp4', false)
        ->assertSee('task_nl_01.mp4', false)
        ->assertSee('users_edit_qr_nl.mp4', false)
        ->assertSee('issue_approve_briefing_nl.mp4', false)
        ->assertSee('unit_categorie_gps_allow_issue_print_qr_nl.mp4', false);
});

it('toont beschikbare promo-video voor franse locale', function () {
    $this->get(route('promo', ['locale' => 'fr']))
        ->assertOk()
        ->assertSee(__('promo.video.qr_portal.title', [], 'fr'))
        ->assertSee(__('promo.video.qr_portal.items.0.title', [], 'fr'))
        ->assertSee('issue_fr_01.mp4', false)
        ->assertSee('task_fr_01.mp4', false);
});

it('toont taalkeuze bovenaan de promo-pagina', function () {
    $this->get(route('promo'))
        ->assertOk()
        ->assertSee(__('common.language.label'), false)
        ->assertSee('images/promo/background.jpg', false)
        ->assertSee('wp-promo-body', false);
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

it('toont drie formules op abonnement', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->addDays(5),
        'has_esg_module' => false,
        'has_time_module' => true,
        'is_active' => true,
    ]);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    Livewire::actingAs($admin)
        ->test(Subscription::class)
        ->assertSee(__('subscription.plans.time.name'))
        ->assertSee(__('subscription.plans.facility.name'))
        ->assertSee(__('subscription.plans.corporate.name'))
        ->assertSee(__('subscription.status_module_time'));
});

it('toont planlabel correct bij billing_plan met hoofdletter', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => null,
        'billing_plan' => 'Facility',
        'billing_active_until' => now()->addMonth(),
        'is_active' => true,
    ]);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    Livewire::actingAs($admin)
        ->test(Subscription::class)
        ->assertSee(__('subscription.plans.facility.name'))
        ->assertDontSee('subscription.plans.Facility.name');

    expect($tenant->effectivePlanKey())->toBe('facility')
        ->and($tenant->hasCsvUnitsImport())->toBeTrue();
});

it('toont csv-import knop op locatie-detail bij Facility-plan met hoofdletter in DB', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => null,
        'billing_plan' => 'Facility',
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
        ->call('activatePlan', 'facility')
        ->assertHasNoErrors();

    $tenant->refresh();

    expect($tenant->billing_plan)->toBe('facility')
        ->and($tenant->billing_active_until)->not->toBeNull()
        ->and($tenant->isPaidSubscriptionActive())->toBeTrue()
        ->and($tenant->isTrialActive())->toBeFalse()
        ->and($tenant->hasTimeModule())->toBeTrue()
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
        ->assertSee(__('faq.items.internal_teams.title'), false);
});

it('toont privacy-document publiek', function () {
    $this->get(route('legal.privacy', ['locale' => 'en']))
        ->assertOk()
        ->assertSee(__('legal.documents.privacy', [], 'en'))
        ->assertSee(__('legal.applicable_law_notice', [], 'en'))
        ->assertSee('QR reports', false)
        ->assertSee('ESG & Compliance', false);
});

it('toont contact voor gasten', function () {
    $this->get(route('contact.index'))
        ->assertOk()
        ->assertSee('info@winprox.app')
        ->assertSee(__('contact.title'));
});
