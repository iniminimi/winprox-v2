<?php

namespace Database\Factories;

use App\Actions\QrCodes\GenerateQrStickerNumberAction;
use App\Enums\QrCodeStatus;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QrCode> */
class QrCodeFactory extends Factory
{
    protected $model = QrCode::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'status' => QrCodeStatus::Unassigned,
            'sticker_number' => fn () => app(GenerateQrStickerNumberAction::class)->handle(),
        ];
    }

    public function unassigned(): static
    {
        return $this->state(['status' => QrCodeStatus::Unassigned]);
    }

    public function active(): static
    {
        return $this->state(['status' => QrCodeStatus::Active]);
    }

    public function damaged(): static
    {
        return $this->state(['status' => QrCodeStatus::Damaged]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => QrCodeStatus::Inactive]);
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state([
            'unit_id' => $unit->id,
            'status' => QrCodeStatus::Active,
            'linked_at' => now(),
        ]);
    }

    /** Vaste token voor tests (productie genereert altijd een willekeurige token). */
    public function withToken(string $token): static
    {
        return $this->afterCreating(function (QrCode $qrCode) use ($token): void {
            $qrCode->forceFill(['token' => $token])->saveQuietly();
        });
    }

    /** Vast sticker nummer voor tests. */
    public function withStickerNumber(string $stickerNumber): static
    {
        return $this->afterCreating(function (QrCode $qrCode) use ($stickerNumber): void {
            $qrCode->forceFill(['sticker_number' => $stickerNumber])->saveQuietly();
        });
    }
}
