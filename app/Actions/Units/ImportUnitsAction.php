<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Actions\Locations\CreateUnitAction;
use App\Data\Units\ImportUnitsData;
use App\Models\Category;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use App\Support\Import\TabularImportBoolean;
use App\Support\Import\TabularImportReader;
use App\Support\Tenant\TenantWorkMenuAccess;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class ImportUnitsAction
{
    /** @var list<string> */
    public const REQUIRED_HEADERS = [
        'unit_name',
    ];

    /** @var list<string> */
    public const OPTIONAL_HEADERS = [
        'description',
        'category_name',
        'external_id',
        'allow_reservations',
        'allow_unit_checks',
        'allow_unit_measurements',
        'require_reporter_contact',
        'require_reporter_email_verification',
        'public_reports_enabled',
    ];

    /** @var list<string> */
    private const BOOLEAN_HEADERS = [
        'allow_reservations',
        'allow_unit_checks',
        'allow_unit_measurements',
        'require_reporter_contact',
        'require_reporter_email_verification',
        'public_reports_enabled',
    ];

    public function __construct(
        private AuditRecorder $audit,
        private CreateUnitAction $createUnit,
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
     * @return array{success: bool, count?: int, batch_id?: string, errors?: list<string>}
     */
    public function handle(ImportUnitsData $data, int $tenantId, ?int $actorUserId = null): array
    {
        $tenant = \App\Models\Tenant::query()->findOrFail($tenantId);
        if (! $tenant->hasCsvUnitsImport()) {
            return [
                'success' => false,
                'errors' => [__('subscription.errors.csv_units_not_allowed')],
            ];
        }

        $location = Location::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($data->locationId)
            ->first();

        if ($location === null) {
            return [
                'success' => false,
                'errors' => [__('locations.units_csv.errors.location_not_found')],
            ];
        }

        try {
            $table = $this->tabularReader->read($data->filePath, $data->originalName);
        } catch (RuntimeException $e) {
            $message = match ($e->getMessage()) {
                'unsupported_import_format' => __('locations.units_csv.errors.unsupported_format'),
                'unreadable' => __('locations.units_csv.errors.unreadable'),
                default => __('locations.units_csv.errors.unreadable'),
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
                'errors' => [__('locations.units_csv.errors.empty')],
            ];
        }

        $missingHeaders = array_diff(self::REQUIRED_HEADERS, $headers);
        if ($missingHeaders !== []) {
            return [
                'success' => false,
                'errors' => [
                    __('locations.units_csv.errors.missing_headers', [
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
                    __('locations.units_csv.errors.unexpected_headers', [
                        'columns' => implode(', ', $unexpectedHeaders),
                    ]),
                ],
            ];
        }

        $headerCount = count($headers);
        $errors = [];
        $validatedRows = [];
        $seenNames = [];
        $seenExternalIds = [];

        foreach ($table['rows'] as $row) {
            $values = array_pad(array_slice($row['values'], 0, $headerCount), $headerCount, '');
            $dataRow = array_combine($headers, $values);
            if ($dataRow === false) {
                continue;
            }

            $rowErrors = $this->validateRow(
                $dataRow,
                $row['line'],
                $headers,
                $location,
                $tenant,
                $seenNames,
                $seenExternalIds,
            );

            if ($rowErrors !== []) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            $validatedRows[] = $dataRow;
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
                'errors' => [__('locations.units_csv.errors.no_rows')],
            ];
        }

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
            $createdCategoryIds = [];

            foreach ($validatedRows as $row) {
                $categoryId = null;
                $categoryName = trim((string) ($row['category_name'] ?? ''));
                if ($categoryName !== '') {
                    $category = Category::firstOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'name' => $categoryName,
                        ],
                        [
                            'is_active' => true,
                        ]
                    );

                    if ($category->wasRecentlyCreated) {
                        $createdCategoryIds[$category->id] = $category->id;
                    }
                    $categoryId = $category->id;
                }

                $unitPayload = $this->buildUnitPayload($row, $headers, $categoryId, $batchId);
                $this->createUnit->handle($location, $unitPayload, $tenantId, $actorUserId);

                $importedCount++;
            }

            $this->logAudit(
                $tenantId,
                $actorUserId,
                $importedCount,
                $batchId,
                $data->originalName,
                array_values($createdCategoryIds),
                $location->id,
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
                'errors' => [__('locations.units_csv.errors.database', ['message' => $e->getMessage()])],
            ];
        }
    }

    /**
     * @param  array<string, string>  $dataRow
     * @param  list<string>  $headers
     * @param  array<string, true>  $seenNames
     * @param  array<string, true>  $seenExternalIds
     * @return list<string>
     */
    private function validateRow(
        array $dataRow,
        int $line,
        array $headers,
        Location $location,
        Tenant $tenant,
        array &$seenNames,
        array &$seenExternalIds,
    ): array {
        $errors = [];

        $validator = Validator::make($dataRow, [
            'unit_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:'.TextDescriptionLimits::MAX,
            'category_name' => 'nullable|string|max:255',
            'external_id' => Schema::hasColumn('units', 'external_id')
                ? ['nullable', 'string', 'max:100']
                : ['nullable'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $errors[] = __('locations.units_csv.errors.row', [
                    'line' => $line,
                    'message' => $error,
                ]);
            }

            return $errors;
        }

        $name = trim((string) $dataRow['unit_name']);
        if (isset($seenNames[$name])) {
            $errors[] = __('locations.units_csv.errors.row', [
                'line' => $line,
                'message' => __('locations.units_csv.errors.duplicate_name'),
            ]);
        } elseif (Unit::query()->where('location_id', $location->id)->where('name', $name)->exists()) {
            $errors[] = __('locations.units_csv.errors.row', [
                'line' => $line,
                'message' => __('locations.units_csv.errors.name_taken'),
            ]);
        } else {
            $seenNames[$name] = true;
        }

        if (in_array('external_id', $headers, true)) {
            $externalId = trim((string) ($dataRow['external_id'] ?? ''));
            if ($externalId !== '') {
                if (isset($seenExternalIds[$externalId])) {
                    $errors[] = __('locations.units_csv.errors.row', [
                        'line' => $line,
                        'message' => __('locations.units_csv.errors.duplicate_external_id'),
                    ]);
                } elseif (Unit::query()->where('tenant_id', $tenant->id)->where('external_id', $externalId)->exists()) {
                    $errors[] = __('locations.units_csv.errors.row', [
                        'line' => $line,
                        'message' => __('locations.units_csv.errors.external_id_taken'),
                    ]);
                } else {
                    $seenExternalIds[$externalId] = true;
                }
            }
        }

        foreach (self::BOOLEAN_HEADERS as $column) {
            if (! in_array($column, $headers, true)) {
                continue;
            }

            $parsed = TabularImportBoolean::parseOptional((string) ($dataRow[$column] ?? ''));
            if (! $parsed['valid']) {
                $errors[] = __('locations.units_csv.errors.row', [
                    'line' => $line,
                    'message' => __('locations.units_csv.errors.invalid_boolean', ['column' => $column]),
                ]);

                continue;
            }

            if ($parsed['value'] === true) {
                if ($column === 'allow_reservations' && ! TenantWorkMenuAccess::reservationsEnabled($tenant)) {
                    $errors[] = __('locations.units_csv.errors.row', [
                        'line' => $line,
                        'message' => __('settings.work_menu.errors.reservations_disabled'),
                    ]);
                }

                if ($column === 'allow_unit_measurements' && ! TenantWorkMenuAccess::unitMeasurementsEnabled($tenant)) {
                    $errors[] = __('locations.units_csv.errors.row', [
                        'line' => $line,
                        'message' => __('settings.work_menu.errors.unit_measurements_disabled'),
                    ]);
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $headers
     * @return array<string, mixed>
     */
    private function buildUnitPayload(array $row, array $headers, ?int $categoryId, string $batchId): array
    {
        $payload = [
            'name' => trim((string) $row['unit_name']),
            'description' => trim((string) ($row['description'] ?? '')) !== ''
                ? trim((string) $row['description'])
                : null,
            'category_id' => $categoryId,
            'import_batch_id' => $batchId,
        ];

        if (in_array('external_id', $headers, true)) {
            $externalId = trim((string) ($row['external_id'] ?? ''));
            if ($externalId !== '') {
                $payload['external_id'] = $externalId;
            }
        }

        foreach (self::BOOLEAN_HEADERS as $column) {
            if (! in_array($column, $headers, true)) {
                continue;
            }

            $parsed = TabularImportBoolean::parseOptional((string) ($row[$column] ?? ''));
            if ($parsed['value'] !== null) {
                $payload[$column] = $parsed['value'];
            }
        }

        return $payload;
    }

    /**
     * @param  list<int>  $createdCategoryIds
     */
    protected function logAudit(
        int $tenantId,
        ?int $actorUserId,
        int $count,
        string $batchId,
        string $fileName,
        array $createdCategoryIds = [],
        ?int $locationId = null,
    ): void {
        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'units.import',
            modelType: Unit::class,
            modelId: null,
            payload: [
                'count' => $count,
                'batch_id' => $batchId,
                'file_name' => $fileName,
                'location_id' => $locationId,
                'created_location_ids' => [],
                'created_category_ids' => $createdCategoryIds,
            ],
        );
    }
}
