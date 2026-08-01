<?php

declare(strict_types=1);

namespace App\Data\Units;

readonly class SaveUnitCheckListData
{
    /**
     * @param  list<string>  $itemLabels
     */
    public function __construct(
        public string $name,
        public array $itemLabels,
        public bool $isActive = true,
        public ?int $internalTeamId = null,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     items?: list<string>|string,
     *     is_active?: bool,
     *     internal_team_id?: int|string|null
     * }  $input
     */
    public static function fromValidated(array $input): self
    {
        $rawItems = $input['items'] ?? [];
        if (is_string($rawItems)) {
            $rawItems = preg_split("/\r\n|\n|\r/", $rawItems) ?: [];
        }

        $labels = [];
        foreach ($rawItems as $item) {
            if (! is_string($item)) {
                continue;
            }
            $label = trim($item);
            if ($label === '') {
                continue;
            }
            $labels[] = $label;
        }

        $teamId = $input['internal_team_id'] ?? null;
        if ($teamId === '' || $teamId === null) {
            $teamId = null;
        } else {
            $teamId = (int) $teamId;
        }

        return new self(
            name: trim((string) $input['name']),
            itemLabels: array_values($labels),
            isActive: (bool) ($input['is_active'] ?? true),
            internalTeamId: $teamId,
        );
    }
}
