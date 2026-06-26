<?php

use App\Livewire\Auth\Register;
use App\Livewire\Pages\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Support\RegisterFormData;

afterEach(fn () => Tenancy::forget());

it('toont de welcome-pagina voor gasten', function () {
    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee(__('welcome.hero.tagline_highlight'))
        ->assertSee('property="og:description" content="'.__('welcome.social.og_description').'"', false)
        ->assertSee('/images/promo/og_1.jpg', false);
});

it('zet open graph meta op de promo-pagina', function () {
    app()->setLocale('en');

    $this->get(route('promo'))
        ->assertOk()
        ->assertSee('property="og:title" content="'.__('promo.social.og_title').'"', false)
        ->assertSee('property="og:description" content="'.__('promo.social.og_description').'"', false)
        ->assertSee('/images/promo/og_1.jpg', false);
});

it('toont beschikbare promo-video per locale', function () {
    $this->withSession(['locale' => 'nl'])
        ->get(route('promo'))
        ->assertOk()
        ->assertSee(__('promo.video.title', [], 'nl'))
        ->assertSee(__('promo.video.beheerportaal.title', [], 'nl'))
        ->assertSee(__('promo.video.beheerportaal.items.0.description', [], 'nl'))
        ->assertDontSee('issue_nl_01.mp4', false)
        ->assertDontSee('task_nl.mp4', false)
        ->assertSee('users_edit_qr_nl.mp4', false)
        ->assertSee('issue_approve_briefing_nl.mp4', false)
        ->assertSee('unit_categorie_gps_allow_issue_print_qr_nl.mp4', false);
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
        ->call('activatePlan', 'pro')
        ->assertHasNoErrors();

    $tenant->refresh();

    expect($tenant->billing_plan)->toBe('pro')
        ->and($tenant->billing_active_until)->not->toBeNull()
        ->and($tenant->isPaidSubscriptionActive())->toBeTrue()
        ->and($tenant->isTrialActive())->toBeFalse();
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
        ->assertSee(__('faq.items.internal_teams.title'), false);
});

it('toont privacy-document publiek', function () {
    $this->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee(__('legal.documents.privacy'))
        ->assertSee(__('legal.applicable_law_notice'))
        ->assertSee('QR-meldingen', false);
});

it('toont contact voor gasten', function () {
    $this->get(route('contact.index'))
        ->assertOk()
        ->assertSee('info@winprox.app')
        ->assertSee(__('contact.title'));
});
