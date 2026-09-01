<?php

namespace App\Actions\Workers;

use App\Data\Workers\ImportWorkersData;
use App\Models\InternalTeam;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use App\Support\Import\TabularImportReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class ImportWorkersAction
{
    public function __construct(
        private AuditRecorder $audit,
        private TabularImportReader $tabularReader,
    ) {}

    /** @var list<string> */
    private const REQUIRED_HEADERS = [
        'team_name',
        'first_name',
        'last_name',
    ];

    /** @var list<string> */
    private const OPTIONAL_HEADERS = [
        'email',
        'phone',
        'company_name',
    ];

    /**
     * @return array{success: bool, count?: int, batch_id?: string, errors?: list<string>}
     */
    public function handle(ImportWorkersData $data, int $tenantId, ?int $actorUserId = null): array
    {
        $tenant = \App\Models\Tenant::query()->findOrFail($tenantId);
        if (! $tenant->hasCsvWorkersImport()) {
            return ['success' => false, 'errors' => [__('subscription.errors.csv_workers_not_allowed')]];
        }

        try {
            $table = $this->tabularReader->read($data->filePath, $data->originalName);
        } catch (RuntimeException $e) {
            $message = match ($e->getMessage()) {
                'unsupported_import_format' => __('team.workers.errors.unsupported_format'),
                'unreadable' => __('team.workers.errors.unreadable'),
                default => __('team.workers.errors.unreadable'),
            };

            return ['success' => false, 'errors' => [$message]];
        }

        $headers = $table['headers'];
        if ($headers === []) {
            return ['success' => false, 'errors' => [__('team.workers.errors.empty')]];
        }

        $missingHeaders = array_diff(self::REQUIRED_HEADERS, $headers);
        if ($missingHeaders !== []) {
            return [
                'success' => false,
                'errors' => [__('team.workers.errors.missing_headers', [
                    'columns' => implode(', ', $missingHeaders),
                ])],
            ];
        }

        $expectedHeaders = array_merge(self::REQUIRED_HEADERS, self::OPTIONAL_HEADERS);
        $unexpectedHeaders = array_diff($headers, $expectedHeaders);
        if ($unexpectedHeaders !== []) {
            return [
                'success' => false,
                'errors' => [__('team.workers.errors.unexpected_headers', [
                    'columns' => implode(', ', $unexpectedHeaders),
                ])],
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
                'team_name' => 'required|string|max:255',
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:64',
                'company_name' => 'nullable|string|max:120',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $errors[] = __('team.workers.errors.row', [
                        'line' => $row['line'],
                        'message' => $error,
                    ]);
                }
            } else {
                $validatedRows[] = $dataRow;
            }
        }

        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        if ($validatedRows === []) {
            return ['success' => false, 'errors' => [__('team.workers.errors.no_rows')]];
        }

        $remainingSeats = $tenant->remainingSeatSlots();
        if ($remainingSeats !== null && count($validatedRows) > $remainingSeats) {
            return ['success' => false, 'errors' => [__('team.errors.seat_limit')]];
        }

        $batchId = (string) Str::uuid();

        DB::beginTransaction();
        try {
            $importedCount = 0;
            $createdTeamIds = [];

            foreach ($validatedRows as $row) {
                $team = InternalTeam::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => trim($row['team_name'])],
                    ['is_active' => true]
                );

                if ($team->wasRecentlyCreated) {
                    $createdTeamIds[$team->id] = $team->id;
                }

                $companyName = self::normalizedCompanyName($row['company_name'] ?? null);

                Worker::create([
                    'tenant_id' => $tenantId,
                    'internal_team_id' => $team->id,
                    'first_name' => trim($row['first_name']),
                    'last_name' => trim($row['last_name']),
                    'email' => isset($row['email']) && $row['email'] !== '' ? trim($row['email']) : null,
                    'phone' => isset($row['phone']) && $row['phone'] !== '' ? trim($row['phone']) : null,
                    'company_name' => $companyName,
                    'is_external' => $companyName !== null,
                    'import_batch_id' => $batchId,
                    'is_active' => true,
                    'is_teamleader' => false,
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
                    'count' => $importedCount,
                    'batch_id' => $batchId,
                    'file_name' => $data->originalName,
                    'created_team_ids' => array_values($createdTeamIds),
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
                'errors' => [__('team.workers.errors.database', ['message' => $e->getMessage()])],
            ];
        }
    }

    private static function normalizedCompanyName(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
