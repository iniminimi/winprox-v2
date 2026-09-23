<?php

declare(strict_types=1);

namespace App\Actions\Locations;

use App\Data\Locations\ImportLocationsData;
use App\Http\Requests\Locations\StoreLocationRequest;
use App\Models\Location;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use App\Support\Import\TabularImportReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class ImportLocationsAction
{
    /** @var list<string> */
    public const OPTIONAL_HEADERS = [
        'name',
        'street',
        'house_number',
        'postal_code',
        'city',
        'country_code',
        'notes',
        'contractual_relationship_reference',
        'latitude',
        'longitude',
    ];

    public function __construct(
        private AuditRecorder $audit,
        private CreateLocationAction $createLocation,
        private TabularImportReader $tabularReader,
    ) {}

    /**
     * @return list<string>
     */
    public static function allHeaders(): array
    {
        return self::OPTIONAL_HEADERS;
    }

    /**
     * @return array{success: bool, count?: int, batch_id?: string, errors?: list<string>}
     */
    public function handle(ImportLocationsData $data, int $tenantId, ?int $actorUserId = null): array
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        if (! $tenant->hasCsvLocationsImport()) {
            return [
                'success' => false,
                'errors' => [__('subscription.errors.csv_locations_not_allowed')],
            ];
        }

        try {
            $table = $this->tabularReader->read($data->filePath, $data->originalName);
        } catch (RuntimeException $e) {
            $message = match ($e->getMessage()) {
                'unsupported_import_format' => __('locations.locations_csv.errors.unsupported_format'),
                'unreadable' => __('locations.locations_csv.errors.unreadable'),
                default => __('locations.locations_csv.errors.unreadable'),
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
                'errors' => [__('locations.locations_csv.errors.empty')],
            ];
        }

        if (! $this->headersAllowIdentity($headers)) {
            return [
                'success' => false,
                'errors' => [__('locations.locations_csv.errors.missing_identity_headers')],
            ];
        }

        $unexpectedHeaders = array_diff($headers, self::allHeaders());
        if ($unexpectedHeaders !== []) {
            return [
                'success' => false,
                'errors' => [
                    __('locations.locations_csv.errors.unexpected_headers', [
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
                'errors' => [__('locations.locations_csv.errors.no_rows')],
            ];
        }

        try {
            $tenant->assertCanAddLocations(count($validatedRows));
        } catch (\InvalidArgumentException) {
            return [
                'success' => false,
                'errors' => [__('locations.errors.location_limit')],
            ];
        }

        // Elke geïmporteerde locatie krijgt automatisch een "Hele locatie"-unit.
        try {
            $tenant->assertCanAddUnits(count($validatedRows));
        } catch (\InvalidArgumentException) {
            return [
                'success' => false,
                'errors' => [__('locations.errors.unit_limit')],
            ];
        }

        $batchId = (string) Str::uuid();

        DB::beginTransaction();
        try {
            $importedCount = 0;

            foreach ($validatedRows as $row) {
                $row['import_batch_id'] = $batchId;
                $row['original_language'] = null;
                $this->createLocation->handle($row, $tenantId, $actorUserId);
                $importedCount++;
            }

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'locations.import',
                modelType: Location::class,
                modelId: null,
                payload: [
                    'count' => $importedCount,
                    'batch_id' => $batchId,
                    'file_name' => $data->originalName,
                ],
            );

            DB::commit();

            return [
                'success' => true,
                'count' => $importedCount,
                'batch_id' => $batchId,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'success' => false,
                'errors' => [__('locations.locations_csv.errors.database', ['message' => $e->getMessage()])],
            ];
        }
    }

    /**
     * @param  list<string>  $headers
     */
    private function headersAllowIdentity(array $headers): bool
    {
        if (in_array('name', $headers, true)) {
            return true;
        }

        return in_array('street', $headers, true)
            && in_array('postal_code', $headers, true)
            && in_array('city', $headers, true);
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

        $validator = Validator::make(
            $payload,
            StoreLocationRequest::ruleSet(),
            StoreLocationRequest::messageSet(),
        );
        StoreLocationRequest::applyMinimumIdentityCheck($validator);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $errors[] = __('locations.locations_csv.errors.row', [
                    'line' => $line,
                    'message' => $error,
                ]);
            }

            return $errors;
        }

        $hasLat = array_key_exists('latitude', $dataRow) && trim((string) $dataRow['latitude']) !== '';
        $hasLng = array_key_exists('longitude', $dataRow) && trim((string) $dataRow['longitude']) !== '';
        if ($hasLat xor $hasLng) {
            $errors[] = __('locations.locations_csv.errors.row', [
                'line' => $line,
                'message' => __('locations.locations_csv.errors.coords_pair_required'),
            ]);
        }

        $dedupeKey = $this->dedupeKey($payload);
        if (isset($seenKeys[$dedupeKey])) {
            $errors[] = __('locations.locations_csv.errors.row', [
                'line' => $line,
                'message' => __('locations.locations_csv.errors.duplicate_row'),
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

        foreach (self::OPTIONAL_HEADERS as $header) {
            if (! array_key_exists($header, $dataRow)) {
                continue;
            }

            $value = trim((string) $dataRow[$header]);
            if ($header === 'country_code') {
                $payload[$header] = $value !== '' ? strtoupper($value) : 'BE';

                continue;
            }

            if ($header === 'latitude' || $header === 'longitude') {
                $payload[$header] = $value !== '' ? $value : null;

                continue;
            }

            $payload[$header] = $value !== '' ? $value : null;
        }

        if (! array_key_exists('country_code', $payload) || $payload['country_code'] === null || $payload['country_code'] === '') {
            $payload['country_code'] = 'BE';
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dedupeKey(array $payload): string
    {
        $name = mb_strtolower(trim((string) ($payload['name'] ?? '')));
        if ($name !== '') {
            return 'name:'.$name;
        }

        return 'address:'.mb_strtolower(implode('|', [
            trim((string) ($payload['street'] ?? '')),
            trim((string) ($payload['house_number'] ?? '')),
            trim((string) ($payload['postal_code'] ?? '')),
            trim((string) ($payload['city'] ?? '')),
            trim((string) ($payload['country_code'] ?? 'BE')),
        ]));
    }
}
