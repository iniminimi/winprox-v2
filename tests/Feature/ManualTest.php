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

it('toont standaard handleiding-screenshots met knop om ze te verbergen', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get(route('manual.general'))
        ->assertOk()
        ->assertSee(__('manual.cover.without_screenshots'))
        ->assertDontSee('wp-manual-root--no-screenshots');
});

it('verbergt handleiding-screenshots wanneer screenshots=0 in de url staat', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get(route('manual.general', ['screenshots' => '0']))
        ->assertOk()
        ->assertSee(__('manual.cover.with_screenshots'))
        ->assertSee('wp-manual-root--no-screenshots', false);
});

it('koppelt inhoudsopgave-links aan het juiste hoofdstuk ook met screenshots zichtbaar', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $html = $this->actingAs($admin)
        ->get(route('manual.general'))
        ->assertOk()
        ->assertSee('wp-manual-screenshot', false)
        ->assertDontSee('wp-manual-root--no-screenshots')
        ->getContent();

    preg_match_all('/href="#(chapter-[^"]+)">([^<]+)</u', $html, $tocMatches, PREG_SET_ORDER);

    expect($tocMatches)->not->toBeEmpty();

    foreach ($tocMatches as [, $chapterId, $tocTitle]) {
        $needle = 'id="'.$chapterId.'"';
        expect(mb_strpos($html, $needle))->not->toBeFalse("Anker '{$chapterId}' ontbreekt in de handleiding.");

        $decodedTitle = html_entity_decode($tocTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $chapterPos = mb_strpos($html, 'id="'.$chapterId.'"');
        $chapterHtml = mb_substr($html, $chapterPos, 4000);
        preg_match('/<h2[^>]*>(.*?)<\/h2>/u', $chapterHtml, $headingMatch);
        $headingTitle = html_entity_decode($headingMatch[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

        expect($headingTitle)->toBe($decodedTitle, "Inhoudsopgave-link '{$decodedTitle}' wijst niet naar hetzelfde hoofdstuk.");
    }
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
        range(1, 34),
    );

    $expectedChapterIds = [
        'chapter-team-backoffice',
        'chapter-team-teams',
        'chapter-locations-categories',
        'chapter-locations-list',
        'chapter-locations-show',
        'chapter-issues-list',
        'chapter-issues-show',
        'chapter-issues-create',
        'chapter-tasks-list',
        'chapter-tasks-show',
        'chapter-calendar',
        'chapter-reservations',
        'chapter-dashboard',
        'chapter-esg-dashboard',
        'chapter-esg-indicators',
        'chapter-esg-measurements',
        'chapter-iot-index',
        'chapter-time-presence',
        'chapter-time-shifts',
        'chapter-time-clock_points',
        'chapter-settings',
        'chapter-settings-api',
        'chapter-subscription',
        'chapter-statuses-admin-portal',
        'chapter-portal-worker-qr',
        'chapter-portal-time',
        'chapter-portal-unit',
        'chapter-portal-team',
        'chapter-portal-worker-photos',
        'chapter-portal-teamleader-role',
        'chapter-portal-teamleader-release',
        'chapter-portal-teamleader-workers',
        'chapter-portal-teamleader-tasks',
        'chapter-statuses-internet-portal',
    ];

    foreach ($expectedAnchors as $anchor) {
        expect($html)->toContain($anchor);
    }

    expect($html)->toContain('API &amp; webhooks');

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

it('toont portaal-statussen als hoofdstukken met pillen en geen statussen per pagina-hoofdstuk', function () {
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

    $adminStatusPos = mb_strpos($html, 'id="chapter-statuses-admin-portal"');
    $internetStatusPos = mb_strpos($html, 'id="chapter-statuses-internet-portal"');
    $settingsApiPos = mb_strpos($html, 'id="chapter-settings-api"');
    $qrPos = mb_strpos($html, 'id="chapter-portal-worker-qr"');
    $lastPortalPos = mb_strpos($html, 'id="chapter-portal-teamleader-tasks"');

    expect($adminStatusPos)->not->toBeFalse()
        ->and($internetStatusPos)->not->toBeFalse()
        ->and($settingsApiPos)->not->toBeFalse()
        ->and($qrPos)->not->toBeFalse()
        ->and($lastPortalPos)->not->toBeFalse()
        ->and($settingsApiPos)->toBeLessThan($adminStatusPos)
        ->and($adminStatusPos)->toBeLessThan($qrPos)
        ->and($qrPos)->toBeLessThan($internetStatusPos)
        ->and($lastPortalPos)->toBeLessThan($internetStatusPos);
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
        ->assertSee('href="#chapter-statuses-admin-portal"', false)
        ->assertSee('href="#chapter-statuses-internet-portal"', false)
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
        ->assertSee('wp-manual-chapter__icon', false)
        ->assertDontSee('wp-manual-toc-panel__icon', false)
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
    $teamsChapterPos = mb_strpos($html, 'id="chapter-team-backoffice"');
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

it('toont gps-geschiedenis-screenshot bij locatiedetail in de algemene handleiding', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get(route('manual.general'))
        ->assertOk()
        ->assertSee('images/manual/nl/locations-gps-history.png', false);
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

it('toont taal-dropdown met alle supported locales op de handleiding', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $response = $this->actingAs($admin)
        ->get(route('manual.general'))
        ->assertOk()
        ->assertSee('wp-lang-select', false);

    foreach (config('locales.labels', []) as $label) {
        $response->assertSee($label, false);
    }
});

it('rendert Italiaanse teksten wanneer ?lang=it wordt meegegeven', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($admin)
        ->get(route('manual.general', ['lang' => 'it']))
        ->assertOk()
        ->assertSee('Segnalazioni', false);
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
        ->assertSee('Clock Point QR');
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
