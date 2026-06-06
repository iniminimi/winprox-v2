<?php

namespace App\Actions\Units;

use App\Models\Category;
use App\Models\Location;
use App\Models\Unit;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ImportUnitsAction
{
    protected array $requiredHeaders = [
        'location_name',
        'unit_name',
    ];

    protected array $optionalHeaders = [
        'description',
        'category_name',
    ];

    /**
     * Import units from CSV file.
     *
     * @param UploadedFile $file
     * @param int|null $actorUserId
     * @return array
     */
    public function handle(UploadedFile $file, ?int $actorUserId = null): array
    {
        $tenantId = Tenancy::id();

        // Open and parse CSV
        $handle = fopen($file->getPathname(), 'r');
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

        // 1. Fail-fast header validation
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

        // Parse all rows
        $handle = fopen($file->getPathname(), 'r');
        fgetcsv($handle); // Skip header row

        $rows = [];
        $lineNumber = 2; // Start at line 2 (after header)

        while (($row = fgetcsv($handle)) !== false) {
            if (array_filter($row) === []) {
                // Skip empty rows
                $lineNumber++;
                continue;
            }

            $data = array_combine($headers, $row);
            $data['_line_number'] = $lineNumber;
            $rows[] = $data;
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
                'description' => 'nullable|string|max:1000',
                'category_name' => 'nullable|string|max:255',
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

            foreach ($validatedRows as $row) {
                // Find or create location
                $location = Location::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'name' => $row['location_name'],
                    ],
                    [
                        'country_code' => 'BE',
                        'is_active' => true,
                    ]
                );

                // Find category if provided
                $categoryId = null;
                if (!empty($row['category_name'])) {
                    $category = Category::where('tenant_id', $tenantId)
                        ->where('name', $row['category_name'])
                        ->first();

                    if ($category) {
                        $categoryId = $category->id;
                    }
                }

                // Create unit
                Unit::create([
                    'tenant_id' => $tenantId,
                    'location_id' => $location->id,
                    'category_id' => $categoryId,
                    'name' => $row['unit_name'],
                    'description' => $row['description'] ?? null,
                    'is_active' => true,
                ]);

                $importedCount++;
            }

            // Audit logging
            $this->logAudit($tenantId, $actorUserId, $importedCount);

            DB::commit();

            return [
                'success' => true,
                'count' => $importedCount,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            return [
                'success' => false,
                'errors' => ['Er is een databasefout opgetreden tijdens het importeren: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Log audit entry for the import.
     */
    protected function logAudit(int $tenantId, ?int $actorUserId, int $count): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $actorUserId,
            'action' => 'units.import',
            'model_type' => Unit::class,
            'model_id' => null,
            'payload' => json_encode(['count' => $count]),
            'created_at' => now(),
        ]);
    }
}
