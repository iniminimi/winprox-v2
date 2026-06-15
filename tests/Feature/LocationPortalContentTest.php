<?php

use App\Livewire\Locations\Announcements;
use App\Livewire\Locations\Documents;
use App\Livewire\Tasks\Index as TaskIndex;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('applies task filters via GO redirect with recurring in URL', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(TaskIndex::class)
        ->set('statusFilter', 'new')
        ->set('recurring', true)
        ->call('applyFilters')
        ->assertRedirect(route('tasks.index', ['status' => 'new', 'recurring' => '1']));
});

it('creates a location document via Livewire', function () {
    Storage::fake('public');

    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);

    Livewire::actingAs($user)
        ->test(Documents::class, ['location' => $location])
        ->call('openCreateModal')
        ->set('description', 'Veiligheidsblad compressor')
        ->set('unitId', (string) $unit->id)
        ->set('documentFile', UploadedFile::fake()->create('veiligheid.pdf', 50, 'application/pdf'))
        ->call('createDocument')
        ->assertHasNoErrors();

    $document = Document::where('location_id', $location->id)->first();

    expect($document)->not->toBeNull()
        ->and($document->description)->toBe('Veiligheidsblad compressor')
        ->and($document->unit_id)->toBe($unit->id)
        ->and(Storage::disk('public')->exists($document->file_path))->toBeTrue();
});

it('toont een Nederlandse foutmelding bij een niet-toegestaan documenttype', function () {
    app()->setLocale('nl');

    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'nl']);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(Documents::class, ['location' => $location])
        ->call('openCreateModal')
        ->set('description', 'Ongeldig bestand')
        ->set('documentFile', UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'))
        ->call('createDocument')
        ->assertHasErrors('documentFile')
        ->assertSee(__('locations.documents.errors.file_mimes'));
});

it('creates a location announcement via Livewire', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(Announcements::class, ['location' => $location])
        ->call('openCreateModal')
        ->set('body', 'Morgen onderhoud in hal A')
        ->call('createAnnouncement')
        ->assertHasNoErrors();

    $announcement = Announcement::where('location_id', $location->id)->first();

    expect($announcement)->not->toBeNull()
        ->and($announcement->body)->toBe('Morgen onderhoud in hal A')
        ->and($announcement->is_active)->toBeTrue();
});
