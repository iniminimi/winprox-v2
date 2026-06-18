<?php

namespace App\Http\Requests\Tasks;

use App\Support\Validation\TextDescriptionLimits;
use App\Enums\TaskStatus;
use App\Support\Tasks\TaskStatusTransitions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::ruleSet();
    }

    /**
     * Herbruikbaar door API, CLI en Livewire (integration-first).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(): array
    {
        return [
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'reason' => ['nullable', 'string', 'max:'.TextDescriptionLimits::MAX],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $task = $this->route('task');
            if (! $task instanceof \App\Models\Task) {
                return;
            }

            $from = $task->status instanceof TaskStatus
                ? $task->status
                : (TaskStatus::tryFrom((string) $task->status) ?? TaskStatus::New);

            $toValue = (string) $this->input('status');
            $to = TaskStatus::tryFrom($toValue);
            if ($to === null) {
                return;
            }

            if (TaskStatusTransitions::requiresReason($from, $to)) {
                $reason = (string) $this->input('reason', '');
                if (trim($reason) === '') {
                    $validator->errors()->add('reason', __('tasks.errors.reason_required'));
                }
            }
        });
    }
}
