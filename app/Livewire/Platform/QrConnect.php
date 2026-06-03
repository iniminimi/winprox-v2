<?php

namespace App\Livewire\Platform;

use App\Actions\QrCodes\LinkQrCodeToUnitAction;
use App\Models\QrCode;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class QrConnect extends Component
{
    public string $token;
    public QrCode $qrCode;
    public ?int $selectedUnitId = null;
    public string $search = '';
    public bool $showSuccess = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->qrCode = QrCode::where('token', $token)->firstOrFail();

        $this->authorize('link', $this->qrCode);
    }

    public function link(): void
    {
        $this->validate([
            'selectedUnitId' => 'required|exists:units,id',
        ]);

        $unit = Unit::withoutGlobalScopes()->findOrFail($this->selectedUnitId);

        // Verify unit belongs to current tenant
        if ($unit->tenant_id !== Auth::user()->tenant_id) {
            $this->addError('selectedUnitId', 'Invalid unit selected');
            return;
        }

        try {
            app(LinkQrCodeToUnitAction::class)->handle(
                $this->qrCode,
                $unit,
                Auth::user()->tenant_id,
                Auth::id()
            );

            $this->showSuccess = true;
        } catch (\InvalidArgumentException $e) {
            $this->addError('selectedUnitId', $e->getMessage());
        }
    }

    public function getUnitsProperty()
    {
        return Unit::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('is_active', true)
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%');
            })
            ->with('location')
            ->orderBy('name')
            ->paginate(20);
    }

    public function redirectToUnit(): void
    {
        if ($this->qrCode->unit_id) {
            $this->redirectRoute('public.unit-portal', ['token' => $this->qrCode->unit->qr_token]);
        }
    }
}
