<?php

namespace App\Livewire\Public;

use App\Actions\QrCodes\LinkQrCodeToUnitAction;
use App\Models\QrCode;
use App\Models\Unit;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.public')]
#[Title('WinProx')]
class UnassignedQrPortal extends Component
{
    use WithPagination;

    public string $token;
    public QrCode $qrCode;
    public int $tenantId;
    public string $stickerNumber = '';
    public string $email = '';
    public string $password = '';
    public bool $showLogin = true;
    public bool $showSuccess = false;
    public ?int $selectedUnitId = null;
    public string $search = '';
    public string $flashMessage = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->qrCode = QrCode::withoutGlobalScopes()->with('unit')->where('token', $token)->firstOrFail();
        $this->tenantId = $this->qrCode->tenant_id;
        $this->stickerNumber = $this->qrCode->sticker_number;

        Tenancy::actAs($this->tenantId);

        // If QR code is already linked, redirect to unit portal
        if ($this->qrCode->unit_id) {
            $this->redirectRoute('public.unit-portal', ['token' => $this->qrCode->unit->qr_token], navigate: true);
            return;
        }

        // If already logged in and has permission, show unit selection
        if (Auth::check() && Auth::user()->can('link', $this->qrCode)) {
            $this->showLogin = false;
        }
    }

    public function booted(): void
    {
        Tenancy::actAs($this->tenantId);
    }

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt([
            'email' => $this->email,
            'password' => $this->password,
            'tenant_id' => $this->tenantId,
        ])) {
            $this->addError('email', __('auth.failed'));
            return;
        }

        $user = Auth::user();

        if (! $user->can('link', $this->qrCode)) {
            Auth::logout();
            $this->addError('email', __('portal.unassigned_qr.no_permission'));
            return;
        }

        // Redirect to refresh component with new session
        $this->redirectRoute('public.unassigned-qr-portal', ['token' => $this->qrCode->token], navigate: true);
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

            // Redirect to refresh component and show success
            $this->redirectRoute('public.unassigned-qr-portal', ['token' => $this->qrCode->token], navigate: true);
        } catch (\InvalidArgumentException $e) {
            $this->addError('selectedUnitId', $e->getMessage());
        }
    }

    public function redirectToUnit(): void
    {
        if ($this->qrCode->unit_id) {
            $this->redirectRoute('public.unit-portal', ['token' => $this->qrCode->unit->qr_token], navigate: true);
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

    public function render()
    {
        Tenancy::actAs($this->tenantId);

        return view('livewire.public.unassigned-qr-portal', [
            'qrCode' => $this->qrCode,
            'units' => $this->showLogin ? collect() : $this->units,
        ]);
    }
}
