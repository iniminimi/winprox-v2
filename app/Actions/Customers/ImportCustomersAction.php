<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Actions\Locations\CreateLocationAction;
use App\Data\Customers\ImportCustomersData;
use App\Http\Requests\Locations\StoreLocationRequest;
use App\Models\Customer;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use App\Support\Import\TabularImportReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class ImportCustomersAction
{
    /** @var list<string> */
    public const REQUIRED_HEADERS = [
        'name',
    ];

    /** @var list<string> */
    public const OPTIONAL_HEADERS = [
        'contact_name',
        'email',
        'phone',
        'location_name',
        'street',
        'house_number',
        'postal_code',
        'city',
        'country_code',
        'contractual_relationship_reference',
        'latitude',
        'longitude',
    ];

    /** @var list<string> */
    private const ADDRESS_HEADERS = [
        'street',
        'house_number',
        'postal_code',
        'city',
        'country_code',
        'contractual_relationship_reference',
        'latitude',
        'longitude',
    ];

    public function __construct(
        private AuditRecorder $audit,
        private CreateCustomerAction $createCustomer,
        private CreateLocationAction $createLocation,
        private TabularImportReader $tabularReader,
    ) {}

    /**
     * @return list<string>
     */
    public static function allHeaders(): array
    {
        return array_merge(self::REQUIRED_HEADERS, self::OPTIONAL_HEADERS);
    }

    /**
     * @return array{success: bool, count?: int, locations_count?: int, reused_count?: int, batch_id?: string, errors?: list<string>}
     */
    public function handle(ImportCustomersData $data, int $tenantId, ?int $actorUserId = null): array
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        if (! $tenant->hasCsvCustomersImport()) {
            return [
                'success' => false,
                'errors' => [__('subscription.errors.csv_customers_not_allowed')],
            ];
        }

        try {
            $table = $this->tabularReader->read($data->filePath, $data->originalName);
        } catch (RuntimeException $e) {
            $message = match ($e->getMessage()) {
                'unsupported_import_format' => __('customers.customers_csv.errors.unsupported_format'),
                'unreadable' => __('customers.customers_csv.errors.unreadable'),
                default => __('customers.customers_csv.errors.unreadable'),
            };

            return [
                'success' => false,
                'errors' => [$message],
            ];
        }

        $headers = $table['headers'];
        if ($headers === []) {
            return [
                'success' => false,
                'errors' => [__('customers.customers_csv.errors.empty')],
            ];
        }

        $missingHeaders = array_diff(self::REQUIRED_HEADERS, $headers);
        if ($missingHeaders !== []) {
            return [
                'success' => false,
                'errors' => [
                    __('customers.customers_csv.errors.missing_headers', [
                        'columns' => implode(', ', $missingHeaders),
                    ]),
                ],
            ];
        }

        $unexpectedHeaders = array_diff($headers, self::allHeaders());
        if ($unexpectedHeaders !== []) {
            return [
                'success' => false,
                'errors' => [
                    __('customers.customers_csv.errors.unexpected_headers', [
                        'columns' => implode(', ', $unexpectedHeaders),
                    ]),
                ],
            ];
        }

        $headerCount = count($headers);
        $errors = [];
        $validatedRows = [];
        $seenKeys = [];

        foreach ($table['rows'] as $row) {
            $values = array_pad(array_slice($row['values'], 0, $headerCount), $headerCount, '');
            $dataRow = array_combine($headers, $values);
            if ($dataRow === false) {
                continue;
            }

            $rowErrors = $this->validateRow($dataRow, $row['line'], $seenKeys);
            if ($rowErrors !== []) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            $validatedRows[] = $this->normalizeRow($dataRow);
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        if ($validatedRows === []) {
            return [
                'success' => false,
                'errors' => [__('customers.customers_csv.errors.no_rows')],
            ];
        }

        $withSiteUnit = ! $tenant->checkmateMode();

        $locationsToCreate = count(array_filter(
            $validatedRows,
            static fn (array $row): bool => $row['has_address'],
        ));

        try {
            $tenant->assertCanAddLocations($locationsToCreate);
        } catch (\InvalidArgumentException) {
            return [
                'success' => false,
                'errors' => [__('locations.errors.location_limit')],
            ];
        }

        if ($withSiteUnit) {
            try {
                $tenant->assertCanAddUnits($locationsToCreate);
            } catch (\InvalidArgumentException) {
                return [
                    'success' => false,
                    'errors' => [__('locations.errors.unit_limit')],
                ];
            }
        }

        $batchId = (string) Str::uuid();

        DB::beginTransaction();
        try {
            $customersByName = Customer::query()
                ->where('tenant_id', $tenantId)
                ->with('locations')
                ->get()
                ->keyBy(static fn (Customer $customer): string => mb_strtolower(trim((string) $customer->name)));

            $createdCount = 0;
            $reusedCount = 0;
            $locationsCount = 0;

            foreach ($validatedRows as $row) {
                $key = mb_strtolower($row['name']);
                $customer = $customersByName->get($key);

                if ($customer instanceof Customer) {
                    $reusedCount++;
                } else {
                    $customer = $this->createCustomer->handle($tenant, [
                        'name' => $row['name'],
                        'contact_name' => $row['contact_name'],
                        'email' => $row['email'],
                        'phone' => $row['phone'],
                        'import_batch_id' => $batchId,
                    ], $actorUserId);
                    $customer->setRelation('locations', collect());
                    $customersByName->put($key, $customer);
                    $createdCount++;
                }

                if ($row['has_address'] && ! $this->customerHasAddress($customer, $row)) {
                    $this->createLocation->handle([
                        'name' => $row['location_name'] ?? $row['name'],
                        'street' => $row['street'],
                        'house_number' => $row['house_number'],
                        'postal_code' => $row['postal_code'],
                        'city' => $row['city'],
                        'country_code' => $row['country_code'],
                        'contractual_relationship_reference' => $row['contractual_relationship_reference'],
                        'latitude' => $row['latitude'],
                        'longitude' => $row['longitude'],
                        'customer_id' => (int) $customer->id,
                        'import_batch_id' => $batchId,
                        'with_site_unit' => $withSiteUnit,
                    ], $tenantId, $actorUserId);
                    $customer->load('locations');
                    $locationsCount++;
                }
            }

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'customers.import',
                modelType: Customer::class,
                modelId: null,
                payload: [
                    'count' => $createdCount,
                    'locations_count' => $locationsCount,
                    'reused_count' => $reusedCount,
                    'batch_id' => $batchId,
                    'file_name' => $data->originalName,
                ],
            );

            DB::commit();

            return [
                'success' => true,
                'count' => $createdCount,
                'locations_count' => $locationsCount,
                'reused_count' => $reusedCount,
                'batch_id' => $batchId,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'success' => false,
                'errors' => [__('customers.customers_csv.errors.database', ['message' => $e->getMessage()])],
            ];
        }
    }

    /**
     * @param  array<string, string>  $dataRow
     * @param  array<string, true>  $seenKeys
     * @return list<string>
     */
    private function validateRow(array $dataRow, int $line, array &$seenKeys): array
    {
        $errors = [];
        $payload = $this->normalizeRow($dataRow);

        $validator = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'location_name' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => __('customers.errors.name_required'),
            'email.email' => __('customers.errors.email_invalid'),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $errors[] = __('customers.customers_csv.errors.row', [
                    'line' => $line,
                    'message' => $error,
                ]);
            }

            return $errors;
        }

        if ($payload['has_address']) {
            $addressRules = StoreLocationRequest::ruleSet();
            $addressValidator = Validator::make($payload, [
                'street' => $addressRules['street'],
                'house_number' => $addressRules['house_number'],
                'postal_code' => $addressRules['postal_code'],
                'city' => $addressRules['city'],
                'country_code' => $addressRules['country_code'],
                'contractual_relationship_reference' => $addressRules['contractual_relationship_reference'],
                'latitude' => $addressRules['latitude'],
                'longitude' => $addressRules['longitude'],
            ], StoreLocationRequest::messageSet());

            if ($addressValidator->fails()) {
                foreach ($addressValidator->errors()->all() as $error) {
                    $errors[] = __('customers.customers_csv.errors.row', [
                        'line' => $line,
                        'message' => $error,
                    ]);
                }
            }

            if ($payload['street'] === null || $payload['postal_code'] === null || $payload['city'] === null) {
                $errors[] = __('customers.customers_csv.errors.row', [
                    'line' => $line,
                    'message' => __('customers.customers_csv.errors.incomplete_address'),
                ]);
            }

            $hasLat = $payload['latitude'] !== null;
            $hasLng = $payload['longitude'] !== null;
            if ($hasLat xor $hasLng) {
                $errors[] = __('customers.customers_csv.errors.row', [
                    'line' => $line,
                    'message' => __('customers.customers_csv.errors.coords_pair_required'),
                ]);
            }
        }

        $dedupeKey = $this->dedupeKey($payload);
        if (isset($seenKeys[$dedupeKey])) {
            $errors[] = __('customers.customers_csv.errors.row', [
                'line' => $line,
                'message' => __('customers.customers_csv.errors.duplicate_row'),
            ]);
        } else {
            $seenKeys[$dedupeKey] = true;
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $dataRow
     * @return array<string, mixed>
     */
    private function normalizeRow(array $dataRow): array
    {
        $payload = [];

        foreach (self::allHeaders() as $header) {
            if (! array_key_exists($header, $dataRow)) {
                continue;
            }

            $value = trim((string) $dataRow[$header]);
            if ($header === 'country_code') {
                $payload[$header] = $value !== '' ? strtoupper($value) : 'BE';

                continue;
            }

            $payload[$header] = $value !== '' ? $value : null;
        }

        $payload['name'] = trim((string) ($payload['name'] ?? ''));
        foreach (self::OPTIONAL_HEADERS as $header) {
            $payload[$header] ??= $header === 'country_code' ? 'BE' : null;
        }
        $payload['has_address'] = $this->hasAddressIntent($dataRow);

        return $payload;
    }

    /**
     * @param  array<string, string>  $dataRow
     */
    private function hasAddressIntent(array $dataRow): bool
    {
        foreach (self::ADDRESS_HEADERS as $header) {
            if (array_key_exists($header, $dataRow) && trim((string) $dataRow[$header]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dedupeKey(array $payload): string
    {
        return mb_strtolower(implode('|', [
            $payload['name'] ?? '',
            $payload['street'] ?? '',
            $payload['house_number'] ?? '',
            $payload['postal_code'] ?? '',
            $payload['city'] ?? '',
            $payload['country_code'] ?? 'BE',
        ]));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function customerHasAddress(Customer $customer, array $row): bool
    {
        $signature = $this->addressSignature($row);

        return $customer->locations->contains(
            fn ($location): bool => $this->addressSignature([
                'street' => $location->street,
                'house_number' => $location->house_number,
                'postal_code' => $location->postal_code,
                'city' => $location->city,
                'country_code' => $location->country_code,
            ]) === $signature
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function addressSignature(array $row): string
    {
        return mb_strtolower(implode('|', [
            trim((string) ($row['street'] ?? '')),
            trim((string) ($row['house_number'] ?? '')),
            trim((string) ($row['postal_code'] ?? '')),
            trim((string) ($row['city'] ?? '')),
            trim((string) ($row['country_code'] ?? 'BE')),
        ]));
    }
}
