<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('laat een admin de handleidingen-hub zien (200)', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get(route('manual.hub'))
        ->assertOk()
        ->assertSee('Handleidingen');
});

it('laat een medewerker de handleidingen-hub zien (200)', function () {
    $tenant = Tenant::factory()->create();
    $employee = User::factory()->employee()->for($tenant)->create();

    $this->actingAs($employee)
        ->get(route('manual.hub'))
        ->assertOk()
        ->assertSee('Handleidingen');
});

it('toont drie handleiding-knoppen op de hub', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get(route('manual.hub'))
        ->assertOk()
        ->assertSee(__('manual.hub.general'))
        ->assertSee(__('manual.hub.workers'))
        ->assertSee(__('manual.hub.teamleaders'))
        ->assertSee(route('manual.general'), false)
        ->assertSee(route('manual.workers'), false)
        ->assertSee(route('manual.teamleaders'), false);
});

it('wijst een niet-geauthenticeerde bezoeker af met redirect naar login', function () {
    $this->get(route('manual.hub'))
        ->assertRedirect(route('login'));
});

it('laat een admin de algemene handleiding zien (200)', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get(route('manual.general'))
        ->assertOk()
        ->assertSee('WinProx Handleiding');
});

it('bevat alle hoofdstukken in de correcte onboarding-volgorde inclusief QR-portaalhulp', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $response = $this->actingAs($admin)
        ->get(route('manual.general'))
        ->assertOk();

    $html = $response->getContent();

    $expectedAnchors = array_map(
        fn (int $n) => 'Hoofdstuk '.$n,
        range(1, 19),
    );

    $expectedTitlesInOrder = [
        'Teams',
        'Locaties',
        'Locatiedetail',
        'Meldingen',
        'Meldingdetail',
        'Terugkerende melding',
        'Taken',
        'Taakdetail',
        'Kalender',
        'Dashboard',
        'Instellingen',
        'Twee QR-codes',
        'Unit QR',
        'Team QR',
        "Foto's bij taken",
        'Teamleader',
        'Icoon collega vrijgeven',
        'Workers beheren',
        'Taken afhandelen',
    ];

    foreach ($expectedAnchors as $anchor) {
        expect($html)->toContain($anchor);
    }

    $positions = [];

    foreach ($expectedTitlesInOrder as $title) {
        $needle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $pos = mb_strpos($html, $needle);
        expect($pos)->not->toBeFalse("Titel '{$title}' niet gevonden in de handleiding.");
        $positions[] = $pos;
    }

    $sorted = $positions;
    sort($sorted);

    expect($positions)->toBe($sorted, 'De volgorde van de hoofdstukken klopt niet met de verwachte onboarding-flow.');
});

it('toont de coverpage met datum en inhoudsopgave', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get(route('manual.general'))
        ->assertOk()
        ->assertSee('Inhoud')
        ->assertSee('Gegenereerd op');
});

it('toont het stappenplan op pagina 2', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get(route('manual.general'))
        ->assertOk()
        ->assertSee('In 5 stappen up-and-running')
        ->assertSee('Teams &amp; uitvoerders aanmaken', false)
        ->assertSee('QR-codes afdrukken');
});

it('bevat geen ruwe "Hulp —" prefix in de hoofdstuktitels', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $html = $this->actingAs($admin)
        ->get(route('manual.general'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('Hulp &mdash;')
        ->and($html)->not->toContain('Hulp — ')
        ->and($html)->not->toContain('Help — ')
        ->and($html)->not->toContain('Aide — ')
        ->and($html)->not->toContain('Hilfe — ');
});

it('rendert Franse teksten wanneer ?lang=fr wordt meegegeven', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get(route('manual.general', ['lang' => 'fr']))
        ->assertOk()
        ->assertSee('Équipes', false)
        ->assertSee('Signalements')
        ->assertSee('Tâches', false);
});

it('rendert de handleiding via de print-layout (geen app-navigatie)', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $response = $this->actingAs($admin)
        ->get(route('manual.general'))
        ->assertOk();

    $html = $response->getContent();

    expect($html)->not->toContain('wp-nav')
        ->and($html)->toContain('no-print')
        ->and($html)->toContain('window.print()');
});

it('toont de uitvoerdershandleiding voor een admin', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get(route('manual.workers'))
        ->assertOk()
        ->assertSee('Handleiding uitvoerders')
        ->assertSee('Twee QR-codes');
});

it('toont de teamleader-handleiding voor een medewerker', function () {
    $tenant = Tenant::factory()->create();
    $employee = User::factory()->employee()->for($tenant)->create();

    $this->actingAs($employee)
        ->get(route('manual.teamleaders'))
        ->assertOk()
        ->assertSee('Handleiding teamleaders')
        ->assertSee('Icoon collega vrijgeven');
});

it('toont de handleiding nav-link in de app-layout voor een admin', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $manualUrl = route('manual.hub');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($manualUrl, false);
});

it('toont de handleiding nav-link in de app-layout voor een medewerker', function () {
    $tenant = Tenant::factory()->create();
    $employee = User::factory()->employee()->for($tenant)->create();

    $manualUrl = route('manual.hub');

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($manualUrl, false);
});
