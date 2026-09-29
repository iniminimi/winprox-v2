<?php

use App\Actions\Customers\DeleteCustomerImportBatchAction;
use App\Actions\Customers\ImportCustomersAction;
use App\Data\Customers\DeleteCustomerImportBatchData;
use App\Data\Customers\ImportCustomersData;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkVisit;
use App\Support\Import\MinimalXlsxWriter;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => Tenancy::forget());
afterEach(fn () => Tenancy::forget());

function runCustomerImport(Tenant $tenant, User $user, string $csvContent): array
{
    $file = UploadedFile::fake()->createWithContent('customers.csv', $csvContent);

    return app(ImportCustomersAction::class)->handle(
        new ImportCustomersData(
            filePath: $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
        ),
        $tenant->id,
        $user->id,
    );
}

it('imports customers from valid CSV', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csv = "name,contact_name,email,phone\n";
    $csv .= "Bakkerij Peeters,Jan Peeters,info@peeters.be,0470123456\n";
    $csv .= "Kapper Sions,,,\n";

    $result = runCustomerImport($tenant, $user, $csv);

    expect($result['success'])->toBeTrue()
        ->and($result['count'])->toBe(2)
        ->and(Customer::where('tenant_id', $tenant->id)->count())->toBe(2)
        ->and(Customer::where('name', 'Bakkerij Peeters')->value('import_batch_id'))->not->toBeNull()
        ->and(DB::table('audit_logs')->where('action', 'customers.import')->exists())->toBeTrue();
});

it('imports a customer with a linked work address', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csv = "name,street,house_number,postal_code,city,country_code\n";
    $csv .= "Bakkerij Peeters,Kerkstraat,12,2000,Antwerpen,BE\n";

    $result = runCustomerImport($tenant, $user, $csv);

    expect($result['success'])->toBeTrue()
        ->and($result['locations_count'])->toBe(1);

    $customer = Customer::where('name', 'Bakkerij Peeters')->firstOrFail();
    $location = Location::where('tenant_id', $tenant->id)->firstOrFail();

    expect($location->customer_id)->toBe($customer->id)
        ->and($location->city)->toBe('Antwerpen')
        ->and($location->import_batch_id)->toBe($result['batch_id']);
});

it('creates one customer for repeated names and adds work addresses', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csv = "name,street,postal_code,city\n";
    $csv .= "Bakkerij Peeters,Kerkstraat,2000,Antwerpen\n";
    $csv .= "Bakkerij Peeters,Mechelsesteenweg,2018,Antwerpen\n";

    $result = runCustomerImport($tenant, $user, $csv);

    expect($result['success'])->toBeTrue()
        ->and($result['count'])->toBe(1)
        ->and($result['locations_count'])->toBe(2)
        ->and(Customer::where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(Location::where('tenant_id', $tenant->id)->count())->toBe(2);
});

it('reuses an existing customer with the same name', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $existing = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Bakkerij Peeters',
    ]);

    $csv = "name,street,postal_code,city\n";
    $csv .= "Bakkerij Peeters,Kerkstraat,2000,Antwerpen\n";

    $result = runCustomerImport($tenant, $user, $csv);

    expect($result['success'])->toBeTrue()
        ->and($result['count'])->toBe(0)
        ->and($result['reused_count'])->toBe(1)
        ->and(Customer::where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and($existing->fresh()->import_batch_id)->toBeNull();

    $location = Location::where('tenant_id', $tenant->id)->firstOrFail();
    expect($location->customer_id)->toBe($existing->id);
});

it('does not duplicate an already-linked work address', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $existing = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Bakkerij Peeters',
    ]);
    Location::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $existing->id,
        'street' => 'Kerkstraat',
        'house_number' => '12',
        'postal_code' => '2000',
        'city' => 'Antwerpen',
        'country_code' => 'BE',
    ]);

    $csv = "name,street,house_number,postal_code,city,country_code\n";
    $csv .= "Bakkerij Peeters,Kerkstraat,12,2000,Antwerpen,BE\n";

    $result = runCustomerImport($tenant, $user, $csv);

    expect($result['success'])->toBeTrue()
        ->and($result['locations_count'])->toBe(0)
        ->and(Location::where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('imports customers from xlsx', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'customers-test-'.uniqid('', true).'.xlsx';
    MinimalXlsxWriter::write($path, [
        ['name', 'street', 'postal_code', 'city'],
        ['Bakkerij Peeters', 'Kerkstraat', '2000', 'Antwerpen'],
    ]);

    try {
        $result = app(ImportCustomersAction::class)->handle(
            new ImportCustomersData(filePath: $path, originalName: 'customers.xlsx'),
            $tenant->id,
            $user->id,
        );
    } finally {
        @unlink($path);
    }

    expect($result['success'])->toBeTrue()
        ->and($result['count'])->toBe(1)
        ->and($result['locations_count'])->toBe(1);
});

it('imports a work address with a DDT reference', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csv = "name,street,postal_code,city,ddt\n";
    $csv .= "Bakkerij Peeters,Kerkstraat,2000,Antwerpen,W123456789012\n";

    $result = runCustomerImport($tenant, $user, $csv);

    expect($result['success'])->toBeTrue();
    expect(Location::where('tenant_id', $tenant->id)->sole()->contractual_relationship_reference)
        ->toBe('W123456789012');
});

it('fails on an invalid DDT reference', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csv = "name,street,postal_code,city,ddt\n";
    $csv .= "Bakkerij Peeters,Kerkstraat,2000,Antwerpen,te-kort\n";

    $result = runCustomerImport($tenant, $user, $csv);

    expect($result['success'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty()
        ->and(Location::where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('fails when the name header is missing', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $result = runCustomerImport($tenant, $user, "email\ninfo@peeters.be\n");

    expect($result['success'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('fails on unknown columns', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $result = runCustomerImport($tenant, $user, "name,foobar\nBakkerij,x\n");

    expect($result['success'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('fails on invalid rows', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csv = "name,email\n";
    $csv .= "Bakkerij Peeters,geen-email\n";

    $result = runCustomerImport($tenant, $user, $csv);

    expect($result['success'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty()
        ->and(Customer::where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('fails on an incomplete work address', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csv = "name,street\n";
    $csv .= "Bakkerij Peeters,Kerkstraat\n";

    $result = runCustomerImport($tenant, $user, $csv);

    expect($result['success'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty()
        ->and(Customer::where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and(Location::where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('fails on duplicate rows in the same file', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csv = "name\n";
    $csv .= "Bakkerij Peeters\n";
    $csv .= "Bakkerij Peeters\n";

    $result = runCustomerImport($tenant, $user, $csv);

    expect($result['success'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('ensures tenant isolation for customer imports', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Tenancy::actAs($tenantA->id);
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

    $result = runCustomerImport($tenantA, $userA, "name\nAlleen A\n");

    expect($result['success'])->toBeTrue();
    expect(Customer::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->where('name', 'Alleen A')->exists())->toBeTrue();
    expect(Customer::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->where('name', 'Alleen A')->exists())->toBeFalse();
});

it('can undo a customer import batch', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csv = "name,street,postal_code,city\n";
    $csv .= "Klant A,Kerkstraat,2000,Antwerpen\n";
    $csv .= "Klant B,,,\n";

    $import = runCustomerImport($tenant, $user, $csv);
    expect($import['success'])->toBeTrue();
    $batchId = $import['batch_id'];

    $result = app(DeleteCustomerImportBatchAction::class)->handle(
        new DeleteCustomerImportBatchData(importBatchId: $batchId),
        $tenant->id,
        $user->id,
    );

    expect($result['success'])->toBeTrue()
        ->and($result['deleted_customers'])->toBe(2)
        ->and($result['deleted_locations'])->toBe(1)
        ->and(Customer::where('tenant_id', $tenant->id)->where('import_batch_id', $batchId)->count())->toBe(0)
        ->and(Location::where('tenant_id', $tenant->id)->where('import_batch_id', $batchId)->count())->toBe(0);
});

it('preserves imported customers and locations with content when undoing', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csv = "name,street,postal_code,city\n";
    $csv .= "Keep Me,Kerkstraat,2000,Antwerpen\n";
    $csv .= "Delete Me,,,\n";

    $import = runCustomerImport($tenant, $user, $csv);
    $batchId = $import['batch_id'];

    $keepLocation = Location::where('import_batch_id', $batchId)->firstOrFail();
    WorkVisit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $keepLocation->id,
    ]);

    $result = app(DeleteCustomerImportBatchAction::class)->handle(
        new DeleteCustomerImportBatchData(importBatchId: $batchId),
        $tenant->id,
        $user->id,
    );

    expect($result['success'])->toBeTrue()
        ->and($result['deleted_customers'])->toBe(1)
        ->and($result['deleted_locations'])->toBe(0)
        ->and(Customer::where('name', 'Keep Me')->exists())->toBeTrue()
        ->and(Customer::where('name', 'Delete Me')->exists())->toBeFalse()
        ->and(Location::where('import_batch_id', $batchId)->exists())->toBeTrue();
});

it('blocks customer import when the plan does not allow it', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->subDay(),
        'billing_plan' => null,
        'billing_active_until' => null,
    ]);
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $result = runCustomerImport($tenant, $user, "name\nBakkerij Peeters\n");

    expect($result['success'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty()
        ->and(Customer::where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('allows customer import on the checkmate plan without site units', function () {
    $tenant = Tenant::factory()->create([
        'checkmate_mode' => true,
        'has_time_module' => true,
        'billing_plan' => 'checkmate',
        'billing_active_until' => now()->addMonth(),
    ]);
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $csv = "name,street,postal_code,city\n";
    $csv .= "Bakkerij Peeters,Kerkstraat,2000,Antwerpen\n";

    $result = runCustomerImport($tenant, $user, $csv);

    expect($result['success'])->toBeTrue()
        ->and($result['count'])->toBe(1)
        ->and($result['locations_count'])->toBe(1)
        ->and(Unit::where('tenant_id', $tenant->id)->count())->toBe(0);
});
