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

class ImportUnitsAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureUnitTranslationSlotsAction $ensureTranslationSlots,
    ) {}

    protected array $requiredHeaders = [
        'location_name',
        'unit_name',
    ];

    protected array $optionalHeaders = [
        'description',
        'category_name',
        'street',
        'house_number',
        'postal_code',
        'city',
        'country_code',
        'notes',
    ];

    /**
     * Import units from CSV file.
     *
     * @param ImportUnitsData $data
     * @param int $tenantId
     * @param int|null $actorUserId
     * @return array
     */
    public function handle(ImportUnitsData $data, int $tenantId, ?int $actorUserId = null): array
    {
        // Generate unique batch ID for this import
        $batchId = (string) \Illuminate\Support\Str::uuid();

        // Open and parse CSV
        $handle = fopen($data->filePath, 'r');
        if ($handle === false) {
            return [
                'success' => false,
                'errors' => ['Kon het bestand niet openen.'],
            ];
        }

        $headers = fgetcsv($handle);
        fclose($handle);

        if ($headers === false) {
            return [
                'success' => false,
                'errors' => ['Het bestand is leeg of ongeldig.'],
            ];
        }

        // Normalize headers (trim, lowercase)
        $headers = array_map(fn($h) => trim(strtolower($h)), $headers);

        // 1. Fail-fast header validation - check for missing required headers
        $missingHeaders = array_diff($this->requiredHeaders, $headers);
        if (!empty($missingHeaders)) {
            return [
                'success' => false,
                'errors' => [
                    sprintf(
                        'De kolommen in uw bestand komen niet overeen met het WinProx-sjabloon. Ontbrekende kolommen: %s',
                        implode(', ', $missingHeaders)
                    ),
                ],
            ];
        }

        // Check for unexpected headers
        $expectedHeaders = array_merge($this->requiredHeaders, $this->optionalHeaders);
        $unexpectedHeaders = array_diff($headers, $expectedHeaders);
        if (!empty($unexpectedHeaders)) {
            return [
                'success' => false,
                'errors' => [
                    sprintf(
                        'De kolommen in uw bestand komen niet overeen met het WinProx-sjabloon. Onverwachte kolommen: %s',
                        implode(', ', $unexpectedHeaders)
                    ),
                ],
            ];
        }

        // Parse all rows
        $handle = fopen($data->filePath, 'r');
        fgetcsv($handle); // Skip header row

        $rows = [];
        $lineNumber = 2; // Start at line 2 (after header)

        while (($row = fgetcsv($handle)) !== false) {
            if (array_filter($row) === []) {
                // Skip empty rows
                $lineNumber++;
                continue;
            }

            // Skip rows with mismatched column count
            if (count($row) !== count($headers)) {
                $lineNumber++;
                continue;
            }

            // Trim row to match header count (handle trailing empty columns)
            $row = array_slice($row, 0, count($headers));

            $dataRow = array_combine($headers, $row);
            $dataRow['_line_number'] = $lineNumber;
            $rows[] = $dataRow;
            $lineNumber++;
        }

        fclose($handle);

        // 2. Content validation (collect all errors)
        $errors = [];
        $validatedRows = [];

        foreach ($rows as $row) {
            $lineNumber = $row['_line_number'];
            unset($row['_line_number']);

            $validator = Validator::make($row, [
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
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $errors[] = "Rij {$lineNumber}: {$error}";
                }
            } else {
                $validatedRows[] = $row;
            }
        }

        // If there are errors, reject the entire file
        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        // 3. Import within a database transaction
        DB::beginTransaction();
        try {
            $importedCount = 0;
            $createdLocationIds = [];
            $createdCategoryIds = [];

            foreach ($validatedRows as $row) {
                // Find or create location
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

                // Find or create category
                $category = Category::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'name' => trim($row['category_name']),
                    ],
                    [
                        'is_active' => true,
                    ]
                );

                if ($category->wasRecentlyCreated) {
                    $createdCategoryIds[$category->id] = $category->id;
                }
                $categoryId = $category->id;

                // Create unit
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

            // Audit logging
            $this->logAudit(
                $tenantId,
                $actorUserId,
                $importedCount,
                $batchId,
                $data->originalName,
                array_values($createdLocationIds),
                array_values($createdCategoryIds),
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
                'errors' => ['Er is een databasefout opgetreden tijdens het importeren: ' . $e->getMessage()],
            ];
        }
    }

    protected function logAudit(int $tenantId, ?int $actorUserId, int $count, string $batchId, string $fileName, array $createdLocationIds = [], array $createdCategoryIds = []): void
    {
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
                'created_location_ids' => $createdLocationIds,
                'created_category_ids' => $createdCategoryIds,
            ],
        );
    }
}
