<?php

use App\Enums\AdminHealthIssueType;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Admin\AdminHealthService;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Storage;

afterEach(fn () => Tenancy::forget());

it('telt alleen actieve locaties en units mee', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    Location::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => false,
        'address' => null,
        'street' => null,
        'postal_code' => null,
        'city' => null,
    ]);

    $activeLocation = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'street' => 'Ok',
        'house_number' => '1',
        'postal_code' => '1000',
        'city' => 'Stad',
        'is_active' => true,
    ]);

    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $activeLocation->id,
        'is_active' => false,
        'background_photo_path' => null,
    ]);

    Storage::fake('public');
    $path = 'units/bg.jpg';
    Storage::disk('public')->put($path, 'x');

    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $activeLocation->id,
        'is_active' => true,
        'background_photo_path' => $path,
    ]);

    Category::factory()->create(['tenant_id' => $tenant->id]);
    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    $report = app(AdminHealthService::class)->report();

    expect($report->totalChecks)->toBe(4)
        ->and($report->issueCount)->toBe(1)
        ->and($report->issues[0]->type)->toBe(AdminHealthIssueType::CategoryMissingTeam);
});
