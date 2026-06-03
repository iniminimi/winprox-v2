<?php

namespace App\Actions\Locations;

use App\Models\Category;
use App\Support\Audit\AuditRecorder;

class UpdateCategoryAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{name: string}  $data
     */
    public function handle(Category $category, array $data, ?int $actorUserId = null): Category
    {
        $category->update([
            'name' => trim($data['name']),
        ]);

        $fresh = $category->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'category.updated',
            modelType: Category::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'name' => $fresh->name],
        );

        return $fresh;
    }
}
