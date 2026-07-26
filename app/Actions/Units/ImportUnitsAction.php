<?php

namespace App\Actions\Units;

use App\Actions\Communication\EnsureUnitTranslationSlotsAction;
use App\Data\Units\ImportUnitsData;
use App\Models\Category;
use App\Models\Location;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use App\Support\Import\TabularImportReader;
use App\Support\Translation\LocaleSupport;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class ImportUnitsAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureUnitTranslationSlotsAction $ensureTranslationSlots,
        private TabularImportReader $tabularReader,
    ) {}

    /** @var list<string> */
    private const REQUIRED_HEADERS = [
        'unit_name',
    ];

    /** @var list<string> */
    private const OPTIONAL_HEADERS = [
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

        $expectedHeaders = array_merge(self::REQUIRED_HEADERS, self::OPTIONAL_HEADERS);
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

        $headerCount = count($headers);
        $errors = [];
        $validatedRows = [];

        foreach ($table['rows'] as $row) {
            $values = array_pad(array_slice($row['values'], 0, $headerCount), $headerCount, '');
            $dataRow = array_combine($headers, $values);
            if ($dataRow === false) {
                continue;
            }

            $validator = Validator::make($dataRow, [
                'unit_name' => 'required|string|max:255',
                'description' => 'nullable|string|max:'.TextDescriptionLimits::MAX,
                'category_name' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $errors[] = __('locations.units_csv.errors.row', [
                        'line' => $row['line'],
                        'message' => $error,
                    ]);
                }
            } else {
                $validatedRows[] = $dataRow;
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
