<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Storage;

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

    $expectedChapterIds = [
        'chapter-team',
        'chapter-locations-list',
        'chapter-locations-show',
        'chapter-issues-list',
        'chapter-issues-show',
        'chapter-issues-create',
        'chapter-tasks-list',
        'chapter-tasks-show',
        'chapter-calendar',
        'chapter-dashboard',
        'chapter-settings',
        'chapter-portal-worker-qr',
        'chapter-portal-unit',
        'chapter-portal-team',
        'chapter-portal-worker-photos',
        'chapter-portal-teamleader-role',
        'chapter-portal-teamleader-release',
        'chapter-portal-teamleader-workers',
        'chapter-portal-teamleader-tasks',
    ];

    foreach ($expectedAnchors as $anchor) {
        expect($html)->toContain($anchor);
    }

    $positions = [];

    foreach ($expectedChapterIds as $chapterId) {
        $needle = 'id="'.$chapterId.'"';
        $pos = mb_strpos($html, $needle);
        expect($pos)->not->toBeFalse("Hoofdstuk-anchor '{$chapterId}' niet gevonden in de handleiding.");
        $positions[] = $pos;
    }

    $sorted = $positions;
    sort($sorted);

    expect($positions)->toBe($sorted, 'De volgorde van de hoofdstukken klopt niet met de verwachte onboarding-flow.');
});

it('toont gecentraliseerde portaal-statussen met pillen en geen statussen per hoofdstuk', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $html = $this->actingAs($admin)
        ->get(route('manual.general'))
        ->assertOk()
        ->assertSee(__('manual.portal_statuses.admin_portal.title'))
        ->assertSee(__('manual.portal_statuses.internet_portal.title'))
        ->assertSee(__('manual.portal_statuses.admin_portal.intro'))
        ->assertSee(__('manual.portal_statuses.internet_portal.intro'))
        ->assertSee('wp-pill wp-pill--new', false)
        ->assertSee('wp-pill wp-pill--progress', false)
        ->assertDontSee('Meldingstatus op het dashboard volgt de status van de gekoppelde taken.')
        ->getContent();

    $adminStatusPos = mb_strpos($html, 'id="section-admin-portal-statuses"');
    $internetStatusPos = mb_strpos($html, 'id="section-internet-portal-statuses"');
    $settingsPos = mb_strpos($html, 'id="chapter-settings"');
    $qrPos = mb_strpos($html, 'id="chapter-portal-worker-qr"');

    expect($adminStatusPos)->not->toBeFalse()
        ->and($internetStatusPos)->not->toBeFalse()
        ->and($settingsPos)->not->toBeFalse()
        ->and($qrPos)->not->toBeFalse()
        ->and($settingsPos)->toBeLessThan($adminStatusPos)
        ->and($adminStatusPos)->toBeLessThan($qrPos)
        ->and($qrPos)->toBeLessThan($internetStatusPos);
});

it('toont tenant logo en naam op de algemene handleiding cover', function () {
    Storage::fake('public');

    $tenant = Tenant::factory()->create(['name' => 'Facility Demo BV']);
    $logoPath = 'tenant-logos/demo.png';
    Storage::disk('public')->put($logoPath, 'logo');
    $tenant->update(['logo_path' => $logoPath]);

    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get(route('manual.general'))
        ->assertOk()
        ->assertSee('Work in Proximity — alle medewerkers')
        ->assertSee('Facility Demo BV')
        ->assertSee('wp-manual-cover__tenant-logo', false)
        ->assertSee(Storage::disk('public')->url($logoPath), false);
});

it('toont de coverpage met datum en inhoudsopgave', function () {
    $tenant = Tenant::factory()->create(['name' => 'Facility Demo BV']);
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get(route('manual.general'))
        ->assertOk()
        ->assertSee('Inhoud')
        ->assertSee('Gegenereerd op')
        ->assertSee(__('manual.toc.admin_portal'))
        ->assertSee(__('manual.toc.internet_portal'))
        ->assertSee('href="#section-admin-portal-statuses"', false)
        ->assertSee('href="#section-internet-portal-statuses"', false)
        ->assertSee(__('manual.portal_statuses.admin_portal.title'))
        ->assertSee(__('manual.portal_statuses.internet_portal.title'))
        ->assertSee('href="#section-admin-portal"', false)
        ->assertSee('href="#section-internet-portal"', false)
        ->assertSee('id="section-admin-portal"', false)
        ->assertSee('id="section-internet-portal"', false)
        ->assertSee(__('manual.sections.admin_portal.title'))
        ->assertSee(__('manual.sections.internet_portal.title'))
        ->assertSee(__('manual.sections.admin_portal.intro'))
        ->assertSee(__('manual.sections.internet_portal.intro'))
        ->assertSee('wp-manual-toc-columns', false)
        ->assertSee('wp-manual-toc-panel', false)
        ->assertSee('wp-manual-toc-panel__icon', false)
        ->assertSee('wp-manual-print-footer', false)
        ->assertSee(__('manual.print_footer', ['title' => 'WinProx Handleiding', 'tenant' => 'Facility Demo BV']))
        ->assertSee(__('manual.cover.print_hint'));
});

it('toont sectie-introducties in de body in dezelfde stijl als het stappenplan', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $html = $this->actingAs($admin)
        ->get(route('manual.general'))
        ->assertOk()
        ->getContent();

    $esc = fn (string $text) => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    $gettingStartedPos = mb_strpos($html, $esc(__('manual.getting_started.title')));
    $adminSectionPos = mb_strpos($html, $esc(__('manual.sections.admin_portal.title')));
    $teamsChapterPos = mb_strpos($html, 'id="chapter-team"');
    $internetSectionPos = mb_strpos($html, $esc(__('manual.sections.internet_portal.title')));
    $qrChapterPos = mb_strpos($html, 'id="chapter-portal-worker-qr"');

    expect($gettingStartedPos)->not->toBeFalse()
        ->and($adminSectionPos)->not->toBeFalse()
        ->and($teamsChapterPos)->not->toBeFalse()
        ->and($internetSectionPos)->not->toBeFalse()
        ->and($qrChapterPos)->not->toBeFalse()
        ->and($gettingStartedPos)->toBeLessThan($adminSectionPos)
        ->and($adminSectionPos)->toBeLessThan($teamsChapterPos)
        ->and($teamsChapterPos)->toBeLessThan($internetSectionPos)
        ->and($internetSectionPos)->toBeLessThan($qrChapterPos);
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

it('rendert html in actieteksten zoals de winprox.app-link bij Welkom', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $html = $this->actingAs($admin)
        ->get(route('manual.general'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('href="https://winprox.app"')
        ->and($html)->not->toContain('&lt;a href=&quot;https://winprox.app&quot;');
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
