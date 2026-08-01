<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Data\Units\SaveUnitCheckListData;
use App\Models\UnitCheckList;
use Illuminate\Validation\ValidationException;

class CopyUnitCheckListFromStarterAction
{
    public function __construct(private SaveUnitCheckListAction $saveList) {}

    public function handle(
        string $starterKey,
        int $tenantId,
        ?int $internalTeamId = null,
        ?int $actorUserId = null,
    ): UnitCheckList {
        $starter = collect(config('unit_check_starters', []))
            ->first(fn (array $row): bool => ($row['key'] ?? null) === $starterKey);

        if (! is_array($starter)) {
            throw ValidationException::withMessages([
                'starter' => [__('unit_checks.lists.errors.invalid_starter')],
            ]);
        }

        $name = (string) __((string) ($starter['name'] ?? ''));
        $items = [];
        foreach ($starter['items'] ?? [] as $itemKey) {
            if (! is_string($itemKey) || $itemKey === '') {
                continue;
            }
            $label = trim((string) __($itemKey));
            if ($label !== '') {
                $items[] = $label;
            }
        }

        if ($name === '' || $items === []) {
            throw ValidationException::withMessages([
                'starter' => [__('unit_checks.lists.errors.invalid_starter')],
            ]);
        }

        return $this->saveList->handle(
            new SaveUnitCheckListData(
                name: $name,
                itemLabels: $items,
                isActive: true,
                internalTeamId: $internalTeamId,
            ),
            $tenantId,
            null,
            $actorUserId,
        );
    }
}
