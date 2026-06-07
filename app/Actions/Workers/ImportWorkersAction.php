<?php

namespace App\Actions\Workers;

use App\Data\Workers\ImportWorkersData;
use App\Models\InternalTeam;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportWorkersAction
{
    public function __construct(private AuditRecorder $audit) {}

    protected array $requiredHeaders = [
        'team_name',
        'first_name',
        'last_name',
    ];

    protected array $optionalHeaders = [
        'email',
        'phone',
        'external_id',
    ];

    /**
     * @return array{success: bool, count?: int, batch_id?: string, errors?: list<string>}
     */
    public function handle(ImportWorkersData $data, int $tenantId, ?int $actorUserId = null): array
    {
        $batchId = (string) Str::uuid();

        $handle = fopen($data->filePath, 'r');
        if ($handle === false) {
            return ['success' => false, 'errors' => ['Kon het bestand niet openen.']];
        }

        $headers = fgetcsv($handle);
        fclose($handle);

        if ($headers === false) {
            return ['success' => false, 'errors' => ['Het bestand is leeg of ongeldig.']];
        }

        $headers = array_map(fn ($h) => trim(strtolower($h)), $headers);
        $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");

        $missingHeaders = array_diff($this->requiredHeaders, $headers);
        if (! empty($missingHeaders)) {
            return [
                'success' => false,
                'errors' => [sprintf(
                    'De kolommen in uw bestand komen niet overeen met het WinProx-sjabloon. Ontbrekende kolommen: %s',
                    implode(', ', $missingHeaders)
                )],
            ];
        }

        $expectedHeaders = array_merge($this->requiredHeaders, $this->optionalHeaders);
        $unexpectedHeaders = array_diff($headers, $expectedHeaders);
        if (! empty($unexpectedHeaders)) {
            return [
                'success' => false,
                'errors' => [sprintf(
                    'De kolommen in uw bestand komen niet overeen met het WinProx-sjabloon. Onverwachte kolommen: %s',
                    implode(', ', $unexpectedHeaders)
                )],
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

            $validator = Validator::make($row, [
                'team_name'   => 'required|string|max:255',
                'first_name'  => 'required|string|max:255',
                'last_name'   => 'required|string|max:255',
                'email'       => 'nullable|email|max:255',
                'phone'       => 'nullable|string|max:64',
                'external_id' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $errors[] = "Rij {$lineNumber}: {$error}";
                }
            } else {
                $validatedRows[] = $row;
            }
        }

        if (! empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        DB::beginTransaction();
        try {
            $importedCount = 0;

            foreach ($validatedRows as $row) {
                $team = InternalTeam::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => trim($row['team_name'])],
                    ['is_active' => true]
                );

                Worker::create([
                    'tenant_id'       => $tenantId,
                    'internal_team_id' => $team->id,
                    'first_name'      => trim($row['first_name']),
                    'last_name'       => trim($row['last_name']),
                    'email'           => isset($row['email']) && $row['email'] !== '' ? trim($row['email']) : null,
                    'phone'           => isset($row['phone']) && $row['phone'] !== '' ? trim($row['phone']) : null,
                    'external_id'     => isset($row['external_id']) && $row['external_id'] !== '' ? trim($row['external_id']) : null,
                    'import_batch_id' => $batchId,
                    'is_active'       => true,
                    'is_teamleader'   => false,
                ]);

                $importedCount++;
            }

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'workers.import',
                modelType: Worker::class,
                modelId: null,
                payload: [
                    'count'     => $importedCount,
                    'batch_id'  => $batchId,
                    'file_name' => $data->originalName,
                ],
            );

            DB::commit();

            return [
                'success'  => true,
                'count'    => $importedCount,
                'batch_id' => $batchId,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'success' => false,
                'errors'  => ['Er is een databasefout opgetreden tijdens het importeren: '.$e->getMessage()],
            ];
        }
    }
}
