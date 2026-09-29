<?php

namespace App\Livewire\Time;

use App\Actions\Time\ConfirmClockDisplayClaimAction;
use App\Actions\Time\DenyClockDisplayClaimAction;
use App\Actions\Time\IssueClockDisplayPairingCodeAction;
use App\Actions\Time\RotateClockPointDisplaySecretAction;
use App\Actions\Time\UnlinkClockPointDisplayAction;
use App\Actions\Time\UpdateClockDisplayScheduleAction;
use App\Models\ClockDisplayClaim;
use App\Models\ClockPoint;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Koppelscherm voor één Clock Point → TFT-klokscherm (ESP32).
 * Route-naam time.clock-displays.* valt buiten de Checkmate-whitelist —
 * schermkoppeling is daar onzichtbaar (404), Clock Point zelf niet.
 */
#[Layout('components.layouts.app')]
#[Title('WinProx')]
class ClockDisplayPair extends Component
{
    use AuthorizesRequests;

    public ClockPoint $clockPoint;

    public ?string $pairingCode = null;

    public bool $confirmUnlink = false;

    public bool $confirmRotate = false;

    public ?string $displayOnFrom = null;

    public ?string $displayOnUntil = null;

    public function mount(ClockPoint $clockPoint): void
    {
        $this->authorize('update', $clockPoint);
        $this->clockPoint = $clockPoint;
        $this->displayOnFrom = $clockPoint->display_on_from !== null
            ? substr((string) $clockPoint->display_on_from, 0, 5)
            : null;
        $this->displayOnUntil = $clockPoint->display_on_until !== null
            ? substr((string) $clockPoint->display_on_until, 0, 5)
            : null;
    }

    public function issueCode(IssueClockDisplayPairingCodeAction $issue): void
    {
        $this->authorize('update', $this->clockPoint);
        $code = $issue->handle($this->clockPoint->fresh(), (int) $this->clockPoint->tenant_id, auth()->id());
        // Toon leesbaar als XXXX-XXXX; opgeslagen staat hij genormaliseerd.
        $this->pairingCode = substr($code, 0, 4).'-'.substr($code, 4);
    }

    public function confirmClaim(int $claimId, ConfirmClockDisplayClaimAction $confirm): void
    {
        $this->authorize('update', $this->clockPoint);

        $claim = $this->resolveClaim($claimId);
        if ($claim === null) {
            session()->flash('time_flash', __('time.clock_displays.errors.claim_not_found'));

            return;
        }

        try {
            $confirm->handle($claim, (int) $this->clockPoint->tenant_id, auth()->id());
        } catch (InvalidArgumentException $e) {
            session()->flash('time_flash', __('time.clock_displays.errors.'.$e->getMessage()));

            return;
        }

        $this->pairingCode = null;
        session()->flash('time_flash', __('time.clock_displays.confirmed'));
    }

    public function denyClaim(int $claimId, DenyClockDisplayClaimAction $deny): void
    {
        $this->authorize('update', $this->clockPoint);

        $claim = $this->resolveClaim($claimId);
        if ($claim === null) {
            session()->flash('time_flash', __('time.clock_displays.errors.claim_not_found'));

            return;
        }

        try {
            $deny->handle($claim, (int) $this->clockPoint->tenant_id, auth()->id());
        } catch (InvalidArgumentException $e) {
            session()->flash('time_flash', __('time.clock_displays.errors.'.$e->getMessage()));

            return;
        }

        session()->flash('time_flash', __('time.clock_displays.denied'));
    }

    public function unlink(UnlinkClockPointDisplayAction $unlink): void
    {
        $this->authorize('update', $this->clockPoint);
        $this->confirmUnlink = false;

        try {
            $unlink->handle($this->clockPoint->fresh(), (int) $this->clockPoint->tenant_id, auth()->id());
        } catch (InvalidArgumentException $e) {
            session()->flash('time_flash', __('time.clock_displays.errors.'.$e->getMessage()));

            return;
        }

        session()->flash('time_flash', __('time.clock_displays.unlinked'));
    }

    public function rotateSecret(RotateClockPointDisplaySecretAction $rotate): void
    {
        $this->authorize('update', $this->clockPoint);
        $this->confirmRotate = false;

        try {
            $rotate->handle($this->clockPoint->fresh(), (int) $this->clockPoint->tenant_id, auth()->id());
        } catch (InvalidArgumentException $e) {
            session()->flash('time_flash', __('time.clock_displays.errors.'.$e->getMessage()));

            return;
        }

        session()->flash('time_flash', __('time.clock_displays.rotated'));
    }

    public function saveSchedule(UpdateClockDisplayScheduleAction $update): void
    {
        $this->authorize('update', $this->clockPoint);

        $validated = $this->validate([
            'displayOnFrom' => ['nullable', 'date_format:H:i', 'required_with:displayOnUntil'],
            'displayOnUntil' => ['nullable', 'date_format:H:i', 'required_with:displayOnFrom'],
        ]);

        try {
            $update->handle(
                $this->clockPoint->fresh(),
                (int) $this->clockPoint->tenant_id,
                auth()->id(),
                $validated['displayOnFrom'] ?: null,
                $validated['displayOnUntil'] ?: null,
            );
        } catch (InvalidArgumentException $e) {
            session()->flash('time_flash', __('time.clock_displays.errors.'.$e->getMessage()));

            return;
        }

        session()->flash('time_flash', __('time.clock_displays.schedule.saved'));
    }

    public function render()
    {
        $point = $this->clockPoint->fresh();
        $pendingClaim = $point?->displayClaims()->activePending()->latest('id')->first();

        return view('livewire.time.clock-display-pair', [
            'point' => $point,
            'pendingClaim' => $pendingClaim,
        ]);
    }

    /** Alleen claims van dí́t punt — claim-id's zijn niet cross-tenant opvraagbaar. */
    private function resolveClaim(int $claimId): ?ClockDisplayClaim
    {
        $claim = ClockDisplayClaim::query()->find($claimId);

        return $claim !== null && (int) $claim->clock_point_id === (int) $this->clockPoint->id
            ? $claim
            : null;
    }
}
