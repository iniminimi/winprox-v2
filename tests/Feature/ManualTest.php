<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('laat een admin de handleiding zien (200)', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get(route('manual.index'))
        ->assertOk()
        ->assertSee('WinProx Handleiding');
});

it('laat een medewerker de handleiding zien (200)', function () {
    $tenant = Tenant::factory()->create();
    $employee = User::factory()->employee()->for($tenant)->create();

    $this->actingAs($employee)
        ->get(route('manual.index'))
        ->assertOk()
        ->assertSee('WinProx Handleiding');
});

it('wijst een niet-geauthenticeerde bezoeker af met redirect naar login', function () {
    $this->get(route('manual.index'))
        ->assertRedirect(route('login'));
});

it('bevat alle 11 hoofdstukken in de correcte onboarding-volgorde', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $response = $this->actingAs($admin)
        ->get(route('manual.index'))
        ->assertOk();

    $html = $response->getContent();

    $expectedAnchors = [
        'Hoofdstuk 1',
        'Hoofdstuk 2',
        'Hoofdstuk 3',
        'Hoofdstuk 4',
        'Hoofdstuk 5',
        'Hoofdstuk 6',
        'Hoofdstuk 7',
        'Hoofdstuk 8',
        'Hoofdstuk 9',
        'Hoofdstuk 10',
        'Hoofdstuk 11',
    ];

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
    ];

    foreach ($expectedAnchors as $anchor) {
        expect($html)->toContain($anchor);
    }

    $positions = [];

    foreach ($expectedTitlesInOrder as $title) {
        $pos = mb_strpos($html, $title);
        expect($pos)->not->toBeFalse("Titel '{$title}' niet gevonden in de handleiding.");
        $positions[] = $pos;
    }

    $sorted = $positions;
    sort($sorted);

    expect($positions)->toBe($sorted, 'De volgorde van de hoofdstukken klopt niet met de verwachte onboarding-flow.');
});

it('toont de coverpage met datum en inhoudsopgave', function () {
    \Carbon\Carbon::setTestNow(now());

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get(route('manual.index'))
        ->assertOk()
        ->assertSee('Inhoud')
        ->assertSee(now()->format('d-m-Y'));

    \Carbon\Carbon::setTestNow();
});

it('toont het stappenplan op pagina 2', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get(route('manual.index'))
        ->assertOk()
        ->assertSee('In 5 stappen up-and-running')
        ->assertSee('Teams &amp; uitvoerders aanmaken', false)
        ->assertSee('QR-codes afdrukken');
});

it('bevat geen ruwe "Hulp —" prefix in de hoofdstuktitels', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $html = $this->actingAs($admin)
        ->get(route('manual.index'))
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
        ->get(route('manual.index', ['lang' => 'fr']))
        ->assertOk()
        ->assertSee('Équipes', false)
        ->assertSee('Signalements')
        ->assertSee('Tâches', false);
});

it('rendert de handleiding via de print-layout (geen app-navigatie)', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $response = $this->actingAs($admin)
        ->get(route('manual.index'))
        ->assertOk();

    $html = $response->getContent();

    expect($html)->not->toContain('wp-nav')
        ->and($html)->toContain('no-print')
        ->and($html)->toContain('window.print()');
});

it('toont de handleiding nav-link in de app-layout voor een admin', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $manualUrl = route('manual.index');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($manualUrl, false);
});

it('toont de handleiding nav-link in de app-layout voor een medewerker', function () {
    $tenant = Tenant::factory()->create();
    $employee = User::factory()->employee()->for($tenant)->create();

    $manualUrl = route('manual.index');

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($manualUrl, false);
});
