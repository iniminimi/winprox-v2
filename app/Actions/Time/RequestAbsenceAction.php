<?php

namespace App\Actions\Time;

use App\Data\Time\RequestAbsenceData;
use App\Enums\AbsenceRequestStatus;
use App\Enums\ShiftTypeKind;
use App\Events\Time\AbsenceRequested;
use App\Models\AbsenceRequest;
use App\Models\AbsenceRequestPhoto;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\AbsenceRequestPhotoStorage;
use App\Support\Time\TimeModuleAccess;
use App\Support\Validation\TextDescriptionLimits;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RequestAbsenceAction
{
    public const MAX_DAYS = 31;

    public const OTHER_MAX_DAYS = 1;

    public const MAX_PHOTOS = 4;

    public function __construct(private AbsenceRequestPhotoStorage $photoStorage) {}

    public function handle(Tenant $tenant, Worker $worker, RequestAbsenceData $data): AbsenceRequest
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        if ((int) $worker->tenant_id !== (int) $tenant->id) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        if (! $data->kind->isRequestableAbsence()) {
            throw new InvalidArgumentException('invalid_kind');
        }

        $from = $this->parseDate($data->dateFrom);
        $to = $this->parseDate($data->dateTo);
        if ($from === null || $to === null) {
            throw new InvalidArgumentException('range_inverted');
        }

        if ($to->lt($from)) {
            throw new InvalidArgumentException('range_inverted');
        }

        $today = CarbonImmutable::now()->startOfDay();
        if ($from->lt($today)) {
            throw new InvalidArgumentException('date_in_past');
        }

        $days = $from->diffInDays($to) + 1;
        if ($data->kind->limitsAbsenceToSingleDay() && $days > self::OTHER_MAX_DAYS) {
            throw new InvalidArgumentException('other_single_day');
        }
        if ($days > self::MAX_DAYS) {
            throw new InvalidArgumentException('range_too_long');
        }

        $shiftType = $this->resolveShiftType((int) $tenant->id, $data);
        if ($shiftType === null) {
            throw new InvalidArgumentException('no_shift_type');
        }

        if ($this->hasOverlappingPending((int) $tenant->id, (int) $worker->id, $from, $to)) {
            throw new InvalidArgumentException('overlapping_pending');
        }

        $halfDay = $data->halfDay && $data->kind === ShiftTypeKind::Leave && $from->equalTo($to);
        if ($data->halfDay && ! $halfDay) {
            throw new InvalidArgumentException('half_day_range');
        }

        $description = $this->normalizeDescription($data->description);
        $photos = $this->photos($data->photos);

        $paths = [];
        foreach ($photos as $photo) {
            $paths[] = $this->photoStorage->storePrecompressedCopy($photo, (int) $tenant->id);
        }

        try {
            $request = DB::transaction(function () use ($tenant, $worker, $data, $shiftType, $from, $to, $halfDay, $description, $paths) {
                $request = AbsenceRequest::query()->create([
                    'tenant_id' => $tenant->id,
                    'worker_id' => $worker->id,
                    'kind' => $data->kind,
                    'shift_type_id' => $shiftType->id,
                    'date_from' => $from->toDateString(),
                    'date_to' => $to->toDateString(),
                    'day_portion' => $halfDay ? 0.5 : 1,
                    'description' => $description,
                    'status' => AbsenceRequestStatus::Pending,
                ]);

                foreach ($paths as $path) {
                    AbsenceRequestPhoto::query()->create([
                        'tenant_id' => $tenant->id,
                        'absence_request_id' => $request->id,
                        'path' => $path,
                    ]);
                }

                return $request;
            });
        } catch (\Throwable $e) {
            foreach ($paths as $path) {
                $this->photoStorage->delete($path);
            }

            throw $e;
        }

        AbsenceRequested::dispatch(
            (int) $tenant->id,
            (int) $request->id,
            (int) $worker->id,
            $data->kind->value,
            $from->toDateString(),
            $to->toDateString(),
            $halfDay ? 0.5 : 1,
        );

        return $request->fresh(['worker', 'shiftType', 'photos']) ?? $request;
    }

    /**
     * @param  list<UploadedFile>  $photos
     * @return list<UploadedFile>
     */
    private function photos(array $photos): array
    {
        $files = [];
        foreach ($photos as $photo) {
            if ($photo instanceof UploadedFile) {
                $files[] = $photo;
            }
        }

        if (count($files) > self::MAX_PHOTOS) {
            throw new InvalidArgumentException('photos_max');
        }

        return $files;
    }

    private function resolveShiftType(int $tenantId, RequestAbsenceData $data): ?ShiftType
    {
        if ($data->shiftTypeId !== null) {
            $type = ShiftType::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($data->shiftTypeId)
                ->where('kind', $data->kind)
                ->where('is_active', true)
                ->first();

            if ($type === null || ! $data->kind->isRequestableAbsence()) {
                return null;
            }

            return $type;
        }

        if ($data->kind === ShiftTypeKind::Other) {
            return null;
        }

        return $this->firstActiveType($tenantId, $data->kind);
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function firstActiveType(int $tenantId, ShiftTypeKind $kind): ?ShiftType
    {
        return ShiftType::query()
            ->where('tenant_id', $tenantId)
            ->where('kind', $kind)
            ->where('is_active', true)
            ->orderBy('code')
            ->first();
    }

    private function hasOverlappingPending(
        int $tenantId,
        int $workerId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): bool {
        return AbsenceRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('worker_id', $workerId)
            ->where('status', AbsenceRequestStatus::Pending)
            ->whereDate('date_from', '<=', $to->toDateString())
            ->whereDate('date_to', '>=', $from->toDateString())
            ->exists();
    }

    private function normalizeDescription(?string $description): ?string
    {
        $trimmed = trim((string) $description);

        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, TextDescriptionLimits::MAX);
    }
}
