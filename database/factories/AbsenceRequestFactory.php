<?php

namespace Database\Factories;

use App\Enums\AbsenceRequestStatus;
use App\Enums\ShiftTypeKind;
use App\Models\AbsenceRequest;
use App\Models\Tenant;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AbsenceRequest> */
class AbsenceRequestFactory extends Factory
{
    protected $model = AbsenceRequest::class;

    public function definition(): array
    {
        $from = now()->addDay()->toDateString();

        return [
            'tenant_id' => Tenant::factory(),
            'worker_id' => Worker::factory(),
            'kind' => ShiftTypeKind::Leave,
            'shift_type_id' => null,
            'date_from' => $from,
            'date_to' => $from,
            'description' => null,
            'status' => AbsenceRequestStatus::Pending,
            'decision_description' => null,
            'decided_by_user_id' => null,
            'decided_at' => null,
            'replaced_shifts' => null,
        ];
    }
}
