<?php

namespace App\Actions\Locations;

use App\Models\Category;
use App\Models\Location;
use App\Support\Audit\AuditRecorder;

class CreateCategoryAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{name: string}  $data
     */
    public function handle(Location $location, array $data, ?int $actorUserId = null): Category
    {
        $category = Category::query()->create([
            'tenant_id' => (int) $location->tenant_id,
            'name' => trim($data['name']),
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $location->tenant_id,
            action: 'category.created',
            modelType: Category::class,
            modelId: (int) $category->id,
            payload: ['id' => $category->id, 'name' => $category->name],
        );

        return $category;
    }
}
