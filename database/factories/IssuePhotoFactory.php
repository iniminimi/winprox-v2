<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\IssuePhoto;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IssuePhoto> */
class IssuePhotoFactory extends Factory
{
    protected $model = IssuePhoto::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'issue_id' => Issue::factory(),
            'path' => 'issue-photos/'.fake()->uuid().'.jpg',
        ];
    }
}
