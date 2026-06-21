<?php

use App\Actions\Communication\EnsureAnnouncementTranslationSlotsAction;
use App\Actions\Communication\TranslateAnnouncementAction;
use App\Actions\Locations\CreateLocationAnnouncementAction;
use App\Actions\Locations\ToggleLocationAnnouncementActiveAction;
use App\Enums\AnnouncementTranslationStatus;
use App\Livewire\Locations\Announcements;
use App\Models\Announcement;
use App\Models\AnnouncementTranslation;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Tenancy;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\FakeTranslationProvider;

afterEach(fn () => Tenancy::forget());

beforeEach(function () {
    app()->instance(TranslationProviderInterface::class, new FakeTranslationProvider);
});

it('seed pending vertaalrijen na aanmaken actieve mededeling', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'nl']);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $announcement = app(CreateLocationAnnouncementAction::class)->handle($location, [
        'description' => 'Morgen onderhoud',
        'unit_id' => null,
        'is_active' => true,
        'expires_at' => null,
        'original_language' => 'nl',
    ], $tenant->id, $user->id);

    $rows = AnnouncementTranslation::query()->where('announcement_id', $announcement->id)->get();
    $expectedLocales = collect(expectedTargetLocales('nl'))->sort()->values()->all();

    expect($rows)->toHaveCount(count($expectedLocales))
        ->and($rows->pluck('locale')->sort()->values()->all())->toBe($expectedLocales)
        ->and($rows->every(fn ($row) => $row->status === AnnouncementTranslationStatus::Pending))->toBeTrue();
});

it('maakt geen vertaalrijen voor inactieve mededeling', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $announcement = app(CreateLocationAnnouncementAction::class)->handle($location, [
        'description' => 'Concept',
        'unit_id' => null,
        'is_active' => false,
        'expires_at' => null,
        'original_language' => 'nl',
    ], $tenant->id);

    expect(AnnouncementTranslation::query()->where('announcement_id', $announcement->id)->count())->toBe(0);
});

it('seed vertaalrijen bij activeren', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $announcement = Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Heropening volgende week',
        'original_language' => 'nl',
        'is_active' => false,
        'published_at' => null,
    ]);

    app(ToggleLocationAnnouncementActiveAction::class)->handle($announcement);

    expect(AnnouncementTranslation::query()->where('announcement_id', $announcement->id)->count())->toBe(count(expectedTargetLocales('nl')));
});

it('vertaalt een mededeling via de provider en slaat op', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $announcement = Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Maintenance next week',
        'original_language' => 'en',
        'is_active' => true,
    ]);

    app(EnsureAnnouncementTranslationSlotsAction::class)->handle($announcement);

    $row = app(TranslateAnnouncementAction::class)->handle($announcement, 'nl', $user->id);

    expect($row->status)->toBe(AnnouncementTranslationStatus::Completed)
        ->and($row->description)->toBe('[nl] Maintenance next week')
        ->and($announcement->fresh()->localizedDescription('nl'))->toBe('[nl] Maintenance next week');
});

it('weigert vertaling voor inactieve mededeling', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $announcement = Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Draft',
        'original_language' => 'nl',
        'is_active' => false,
    ]);

    app(TranslateAnnouncementAction::class)->handle($announcement, 'en');
})->throws(ValidationException::class);

it('slaat brontaal op bij aanmaken via Livewire', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'fr']);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(Announcements::class, ['location' => $location])
        ->call('openCreateModal')
        ->set('description', 'Travaux demain')
        ->call('createAnnouncement')
        ->assertHasNoErrors();

    $announcement = Announcement::where('location_id', $location->id)->first();

    expect($announcement)->not->toBeNull()
        ->and($announcement->original_language)->toBe('fr')
        ->and(AnnouncementTranslation::query()->where('announcement_id', $announcement->id)->count())->toBe(count(expectedTargetLocales('fr')));
});

it('exporteert en importeert pending mededelingvertalingen', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $announcement = app(CreateLocationAnnouncementAction::class)->handle($location, [
        'description' => 'Morgen onderhoud',
        'unit_id' => null,
        'is_active' => true,
        'expires_at' => null,
        'original_language' => 'nl',
    ], $tenant->id);

    $exportItems = array_merge(
        app(\App\Actions\Communication\ExportPendingIssueTranslationsAction::class)->handle()['items'],
        app(\App\Actions\Communication\ExportPendingAnnouncementTranslationsAction::class)->handle(),
    );

    expect($exportItems)->toHaveCount(count(expectedTargetLocales('nl')))
        ->and(collect($exportItems)->every(fn ($item) => isset($item['announcement_id'])))->toBeTrue();

    $imported = app(\App\Actions\Communication\ImportAnnouncementTranslationsAction::class)->handle([
        [
            'announcement_id' => $announcement->id,
            'locale' => 'en',
            'description' => 'Maintenance tomorrow',
        ],
    ]);

    expect($imported)->toBe(1)
        ->and($announcement->fresh()->localizedDescription('en'))->toBe('Maintenance tomorrow');
});

it('weigert import van te lange mededelingvertalingen', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $announcement = Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Kort',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureAnnouncementTranslationSlotsAction::class)->handle($announcement);

    expect(fn () => app(\App\Actions\Communication\ImportAnnouncementTranslationsAction::class)->handle([
        [
            'announcement_id' => $announcement->id,
            'locale' => 'en',
            'description' => str_repeat('x', 1501),
        ],
    ]))->toThrow(ValidationException::class);
});

it('zet vertalingen terug naar pending bij wijziging van de brontekst', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $announcement = Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Oude tekst',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureAnnouncementTranslationSlotsAction::class)->handle($announcement);
    app(TranslateAnnouncementAction::class)->handle($announcement, 'en', $user->id);

    expect(AnnouncementTranslation::query()->where('announcement_id', $announcement->id)->where('locale', 'en')->first())
        ->status->toBe(AnnouncementTranslationStatus::Completed);

    app(\App\Actions\Locations\UpdateLocationAnnouncementAction::class)->handle($location, $announcement, [
        'description' => 'Nieuwe tekst',
        'unit_id' => null,
        'is_active' => true,
        'expires_at' => null,
    ], $tenant->id, $user->id);

    $english = AnnouncementTranslation::query()
        ->where('announcement_id', $announcement->id)
        ->where('locale', 'en')
        ->first();

    expect($english->status)->toBe(AnnouncementTranslationStatus::Pending)
        ->and($english->description)->toBeNull();
});

it('toont vertaal-preview in bewerk-modal', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'nl']);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    $announcement = Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Morgen onderhoud',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureAnnouncementTranslationSlotsAction::class)->handle($announcement);
    app(TranslateAnnouncementAction::class)->handle($announcement, 'en', $user->id);

    Livewire::actingAs($user)
        ->test(Announcements::class, ['location' => $location])
        ->call('openEditModal', $announcement->id)
        ->set('previewLocale', 'en')
        ->assertSee('[en] Morgen onderhoud');
});

it('toont vertaalde mededeling in beheerlijst volgens gebruikerstaal', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'en']);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    $announcement = Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Morgen onderhoud',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureAnnouncementTranslationSlotsAction::class)->handle($announcement);
    app(TranslateAnnouncementAction::class)->handle($announcement, 'en', $user->id);

    Livewire::actingAs($user)
        ->test(Announcements::class, ['location' => $location])
        ->assertSee('[en] Morgen onderhoud');
});

it('opent bewerk-modal met vertaling in gebruikerstaal', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'en']);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    $announcement = Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'description' => 'Morgen onderhoud',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureAnnouncementTranslationSlotsAction::class)->handle($announcement);
    app(TranslateAnnouncementAction::class)->handle($announcement, 'en', $user->id);

    Livewire::actingAs($user)
        ->test(Announcements::class, ['location' => $location])
        ->call('openEditModal', $announcement->id)
        ->assertSet('previewLocale', 'en')
        ->assertSee('[en] Morgen onderhoud');
});
