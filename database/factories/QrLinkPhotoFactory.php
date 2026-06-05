<?php

namespace Database\Factories;

use App\Models\QrCode;
use App\Models\QrLinkPhoto;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class QrLinkPhotoFactory extends Factory
{
    protected $model = QrLinkPhoto::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'qr_code_id' => QrCode::factory(),
            'unit_id' => Unit::factory(),
            'path' => 'qr-link-photos/' . $this->faker->uuid . '.jpg',
        ];
    }
}
