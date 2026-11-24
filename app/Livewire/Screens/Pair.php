<?php

declare(strict_types=1);

namespace App\Livewire\Screens;

use App\Actions\Screens\ConfirmScreenClaimAction;
use App\Actions\Screens\DenyScreenClaimAction;
use App\Actions\Screens\IssueScreenPairingCodeAction;
use App\Actions\Screens\UnlinkScreenAction;
use App\Models\Screen;
use App\Models\ScreenClaim;
use App\Models\Unit;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Pair extends Component
{
    public int $unitId;

    public ?string $pairingCode = null;

    public bool $confirmUnlink = false;

    public function mount(Unit $unit): void
    {
        $this->authorize('create', Screen::class);

        if ((int) $unit->tenant_id !== (int) Tenancy::id()) {
            abort(404);
        }

        $this->unitId = $unit->id;
    }

    public function issueCode(IssueScreenPairingCodeAction $issue): void
    {
        $this->authorize('create', Screen::class);
        $unit = $this->unit();
        $this->pairingCode = $issue->handle($unit, (int) Tenancy::id(), auth()->id());
        session()->flash('screen_flash', __('screen.flash.issued'));
    }

    public function confirmClaim(int $claimId, ConfirmScreenClaimAction $confirm): void
    {
        $claim = $this->claim($claimId);
        $this->authorize('update', $claim->screen);
        $confirm->handle($claim, (int) Tenancy::id(), auth()->id());
        $this->pairingCode = null;
        session()->flash('screen_flash', __('screen.flash.confirmed'));
    }

    public function denyClaim(int $claimId, DenyScreenClaimAction $deny): void
    {
        $claim = $this->claim($claimId);
        $this->authorize('update', $claim->screen);
        $deny->handle($claim, (int) Tenancy::id(), auth()->id());
        session()->flash('screen_flash', __('screen.flash.denied'));
    }

    public function unlink(UnlinkScreenAction $unlink): void
    {
        $screen = $this->unit()->screen;
        if ($screen === null) {
            return;
        }

        $this->authorize('update', $screen);
        $unlink->handle($screen, (int) Tenancy::id(), auth()->id());
        $this->confirmUnlink = false;
        $this->pairingCode = null;
        session()->flash('screen_flash', __('screen.flash.unlinked'));
    }

    public function render()
    {
        $unit = $this->unit();
        $screen = $unit->screen;
        $pending = $screen === null
            ? null
            : $screen->claims()->activePending()->latest('id')->first();

        return view('livewire.screens.pair', [
            'unit' => $unit,
            'screen' => $screen,
            'pendingClaim' => $pending,
        ]);
    }

    private function unit(): Unit
    {
        return Unit::query()->with('screen')->findOrFail($this->unitId);
    }

    private function claim(int $claimId): ScreenClaim
    {
        return ScreenClaim::query()
            ->where('tenant_id', Tenancy::id())
            ->with('screen')
            ->findOrFail($claimId);
    }
}
