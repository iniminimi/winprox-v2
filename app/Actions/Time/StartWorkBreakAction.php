<?php

namespace App\Actions\Time;

use App\Enums\BreakType;
use App\Enums\WorkShiftStatus;
use App\Models\Worker;
use App\Models\WorkBreak;
use App\Models\WorkShift;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StartWorkBreakAction
{
    public function handle(Worker $worker, WorkShift $shift): WorkBreak
    {
        if ((int) $worker->id !== (int) $shift->worker_id) {
            throw new InvalidArgumentException('shift_worker_mismatch');
        }

        return DB::transaction(function () use ($worker, $shift) {
            $shift = WorkShift::query()
                ->whereKey($shift->id)
                ->where('worker_id', $worker->id)
                ->where('status', WorkShiftStatus::Open)
                ->lockForUpdate()
                ->first();

            if ($shift === null) {
                throw new InvalidArgumentException('shift_not_open');
            }

            $openBreak = WorkBreak::query()
                ->where('work_shift_id', $shift->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($openBreak !== null) {
                throw new InvalidArgumentException('break_already_open');
            }

            return WorkBreak::create([
                'tenant_id' => $shift->tenant_id,
                'work_shift_id' => $shift->id,
                'started_at' => now(),
                'break_type' => BreakType::Break,
            ]);
        });
    }
}
