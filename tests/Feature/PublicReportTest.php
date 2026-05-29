<?php

use App\Livewire\Public\Report;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('maakt een niet-goedgekeurde melding met foto’s via een geldig unit-token', function () {
    Storage::fake('public');

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'qr_token' => 'test-unit-token',
    ]);

    Livewire::test(Report::class, ['token' => 'test-unit-token'])
        ->set('description', 'De kraan lekt al dagen in de keuken.')
        ->set('photos', [
            UploadedFile::fake()->create('foto1.jpg', 120, 'image/jpeg'),
            UploadedFile::fake()->create('foto2.jpg', 120, 'image/jpeg'),
        ])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $issue = Issue::first();

    expect($issue)->not->toBeNull()
        ->and($issue->tenant_id)->toBe($tenant->id)
        ->and($issue->unit_id)->toBe($unit->id)
        ->and($issue->location_id)->toBe($location->id)
        ->and($issue->isApproved())->toBeFalse()
        ->and($issue->approved_at)->toBeNull()
        ->and($issue->photos()->count())->toBe(2);

    foreach ($issue->photos as $photo) {
        Storage::disk('public')->assertExists($photo->path);
    }
});

it('vereist een omschrijving voor een publieke melding', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'qr_token' => 'token-zonder-tekst',
    ]);

    Livewire::test(Report::class, ['token' => 'token-zonder-tekst'])
        ->set('description', '')
        ->call('submit')
        ->assertHasErrors('description');

    expect(Issue::count())->toBe(0);
});

it('geeft 404 voor een onbekend unit-token', function () {
    $this->get('/melden/bestaat-niet')->assertNotFound();
});
