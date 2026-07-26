<?php

namespace App\Actions\Units;

use App\Actions\Communication\EnsureUnitTranslationSlotsAction;
use App\Data\Units\ImportUnitsData;
use App\Models\Category;
use App\Models\Location;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportUnitsAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureUnitTranslationSlotsAction $ensureTranslationSlots,
    ) {}

    /** @var list<string> */
    private const ORG_REQUIRED_HEADERS = [
        'location_name',
        'unit_name',
    ];

    /** @var list<string> */
    private const ORG_OPTIONAL_HEADERS = [
        'description',
        'category_name',
        'street',
        'house_number',
        'postal_code',
        'city',
        'country_code',
        'notes',
    ];

    /** @var list<string> */
    private const LOCATION_REQUIRED_HEADERS = [
        'unit_name',
    ];

    /** @var list<string> */
    private const LOCATION_OPTIONAL_HEADERS = [
        'description',
        'category_name',
    ];

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

        $scopedLocation = null;
        if ($data->locationId !== null) {
            $scopedLocation = Location::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($data->locationId)
                ->first();

            if ($scopedLocation === null) {
                return [
                    'success' => false,
                    'errors' => [__('locations.units_csv.errors.location_not_found')],
                ];
            }
        }

        $requiredHeaders = $scopedLocation !== null
            ? self::LOCATION_REQUIRED_HEADERS
            : self::ORG_REQUIRED_HEADERS;
        $optionalHeaders = $scopedLocation !== null
            ? self::LOCATION_OPTIONAL_HEADERS
            : self::ORG_OPTIONAL_HEADERS;

        $handle = fopen($data->filePath, 'r');
        if ($handle === false) {
            return [
                'success' => false,
                'errors' => [__('locations.units_csv.errors.unreadable')],
            ];
        }

        $headers = fgetcsv($handle);
        fclose($handle);

        if ($headers === false) {
            return [
                'success' => false,
                'errors' => [__('locations.units_csv.errors.empty')],
            ];
        }

        $headers = array_map(fn ($h) => trim(strtolower((string) $h)), $headers);

        $missingHeaders = array_diff($requiredHeaders, $headers);
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

        $expectedHeaders = array_merge($requiredHeaders, $optionalHeaders);
        $unexpectedHeaders = array_diff($headers, $expectedHeaders);
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

        $handle = fopen($data->filePath, 'r');
        fgetcsv($handle);

        $rows = [];
        $lineNumber = 2;

        while (($row = fgetcsv($handle)) !== false) {
            if (array_filter($row) === []) {
                $lineNumber++;
                continue;
            }

            if (count($row) !== count($headers)) {
                $lineNumber++;
                continue;
            }

            $row = array_slice($row, 0, count($headers));
            $dataRow = array_combine($headers, $row);
            $dataRow['_line_number'] = $lineNumber;
            $rows[] = $dataRow;
            $lineNumber++;
        }

        fclose($handle);

        $errors = [];
        $validatedRows = [];

        foreach ($rows as $row) {
            $lineNumber = $row['_line_number'];
            unset($row['_line_number']);

            $rules = $scopedLocation !== null
                ? [
                    'unit_name' => 'required|string|max:255',
                    'description' => 'nullable|string|max:'.TextDescriptionLimits::MAX,
                    'category_name' => 'nullable|string|max:255',
                ]
                : [
                    'location_name' => 'required|string|max:255',
                    'unit_name' => 'required|string|max:255',
                    'description' => 'nullable|string|max:'.TextDescriptionLimits::MAX,
                    'category_name' => 'required|string|max:255',
                    'street' => 'nullable|string|max:255',
                    'house_number' => 'nullable|string|max:50',
                    'postal_code' => 'nullable|string|max:20',
                    'city' => 'nullable|string|max:255',
                    'country_code' => 'nullable|string|max:2',
                    'notes' => 'nullable|string|max:2000',
                ];

            $validator = Validator::make($row, $rules);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $errors[] = __('locations.units_csv.errors.row', [
                        'line' => $lineNumber,
                        'message' => $error,
                    ]);
                }
            } else {
                $validatedRows[] = $row;
            }
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
            $createdLocationIds = [];
            $createdCategoryIds = [];

            foreach ($validatedRows as $row) {
                if ($scopedLocation !== null) {
                    $location = $scopedLocation;
                } else {
                    $location = Location::firstOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'name' => $row['location_name'],
                        ],
                        [
                            'country_code' => $row['country_code'] ?? 'BE',
                            'street' => $row['street'] ?? null,
                            'house_number' => $row['house_number'] ?? null,
                            'postal_code' => $row['postal_code'] ?? null,
                            'city' => $row['city'] ?? null,
                            'notes' => $row['notes'] ?? null,
                            'is_active' => true,
                        ]
                    );

                    if ($location->wasRecentlyCreated) {
                        $createdLocationIds[$location->id] = $location->id;
                    }
                }

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

                $unit = Unit::create([
                    'tenant_id' => $tenantId,
                    'location_id' => $location->id,
                    'category_id' => $categoryId,
                    'name' => $row['unit_name'],
                    'description' => $row['description'] ?? null,
                    'original_language' => LocaleSupport::normalize(null),
                    'import_batch_id' => $batchId,
                    'is_active' => true,
                ]);

                $this->ensureTranslationSlots->handle($unit);

                $importedCount++;
            }

            $this->logAudit(
                $tenantId,
                $actorUserId,
                $importedCount,
                $batchId,
                $data->originalName,
                array_values($createdLocationIds),
                array_values($createdCategoryIds),
                $scopedLocation?->id,
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
     * @param  list<int>  $createdLocationIds
     * @param  list<int>  $createdCategoryIds
     */
    protected function logAudit(
        int $tenantId,
        ?int $actorUserId,
        int $count,
        string $batchId,
        string $fileName,
        array $createdLocationIds = [],
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
                'created_location_ids' => $createdLocationIds,
                'created_category_ids' => $createdCategoryIds,
            ],
        );
    }
}
