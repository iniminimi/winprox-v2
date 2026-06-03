<?php

namespace App\Actions\Locations;

use App\Models\Category;
use App\Support\Audit\AuditRecorder;

class DeleteCategoryAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Category $category, ?int $actorUserId = null): void
    {
        $tenantId = (int) $category->tenant_id;
        $categoryId = (int) $category->id;
        $name = (string) $category->name;
        $category->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'category.deleted',
            modelType: Category::class,
            modelId: $categoryId,
            payload: ['id' => $categoryId, 'name' => $name],
        );
    }
}
