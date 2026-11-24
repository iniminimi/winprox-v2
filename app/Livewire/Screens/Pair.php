<?php

declare(strict_types=1);

namespace App\Livewire\Screens;

use App\Actions\Screens\ConfirmScreenClaimAction;
use App\Actions\Screens\DenyScreenClaimAction;
use App\Actions\Screens\DisableSenseOnScreenAction;
use App\Actions\Screens\EnableSenseOnScreenAction;
use App\Actions\Screens\EnsureUnitScreenAction;
use App\Actions\Screens\IssueScreenPairingCodeAction;
use App\Actions\Screens\UnlinkScreenAction;
use App\Actions\Screens\UpdateScreenAlbumAction;
use App\Actions\Screens\UpdateScreenScheduleAction;
use App\Actions\Screens\UpdateScreenSoundAction;
use App\Actions\Screens\UploadScreenImageAction;
use App\Actions\Time\DeleteClockDisplayImageAction;
use App\Actions\Time\UpdateClockDisplayAlbumWindowsAction;
use App\Models\ClockDisplayImage;
use App\Models\Screen;
use App\Models\ScreenClaim;
use App\Models\Unit;
use App\Support\Qr\QrSvg;
use App\Support\Screens\ScreenQr;
use App\Support\Tenancy;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class Pair extends Component
{
    use WithFileUploads;

    public int $unitId;

    public ?string $pairingCode = null;

    public bool $confirmUnlink = false;

    public ?string $displayOnFrom = null;

    public ?string $displayOnUntil = null;

    public bool $displaySoundEnabled = true;

    public ?TemporaryUploadedFile $albumPhoto = null;

    public ?string $album1From = null;

    public ?string $album1Until = null;

    public ?string $album2From = null;

    public ?string $album2Until = null;

    public string $albumMode = 'none';

    /** @var array<int, bool> */
    public array $albumDays = [true, true, true, true, true, false, false];

    public function mount(Unit $unit, EnsureUnitScreenAction $ensure): void
    {
        $this->authorize('create', Screen::class);

        if ((int) $unit->tenant_id !== (int) Tenancy::id()) {
            abort(404);
        }

        $this->unitId = $unit->id;
        $screen = $ensure->handle($unit, (int) Tenancy::id());
        $this->fillFromScreen($screen);
    }

    public function toggleSense(EnableSenseOnScreenAction $enable, DisableSenseOnScreenAction $disable): void
    {
        $screen = $this->screen();
        $this->authorize('create', \App\Models\SensePoint::class);

        if ($screen->has_sense) {
            $disable->handle($screen, (int) Tenancy::id(), auth()->id());

            return;
        }

        $enable->handle($screen, (int) Tenancy::id(), auth()->id());
    }

    public function issueCode(IssueScreenPairingCodeAction $issue): void
    {
        $this->authorize('create', Screen::class);
        $this->pairingCode = $issue->handle($this->unit(), (int) Tenancy::id(), auth()->id());
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

    public function saveSchedule(UpdateScreenScheduleAction $update): void
    {
        $screen = $this->screen();
        $this->authorize('update', $screen);

        $validated = $this->validate([
            'displayOnFrom' => ['nullable', 'date_format:H:i', 'required_with:displayOnUntil'],
            'displayOnUntil' => ['nullable', 'date_format:H:i', 'required_with:displayOnFrom'],
        ]);

        try {
            $update->handle(
                $screen,
                (int) Tenancy::id(),
                auth()->id(),
                $validated['displayOnFrom'] ?: null,
                $validated['displayOnUntil'] ?: null,
            );
        } catch (InvalidArgumentException $e) {
            session()->flash('screen_flash', __('time.clock_displays.errors.'.$e->getMessage()));

            return;
        }

        session()->flash('screen_flash', __('time.clock_displays.schedule.saved'));
    }

    public function updatedDisplaySoundEnabled(bool $enabled, UpdateScreenSoundAction $update): void
    {
        $screen = $this->screen();
        $this->authorize('update', $screen);
        $update->handle($screen, (int) Tenancy::id(), auth()->id(), $enabled);
        session()->flash('screen_flash', __('time.clock_displays.sound.saved'));
    }

    public function saveAlbumWindows(UpdateScreenAlbumAction $update): void
    {
        $screen = $this->screen();
        $this->authorize('update', $screen);

        $this->validate([
            'album1From' => ['nullable', 'date_format:H:i', 'required_with:album1Until'],
            'album1Until' => ['nullable', 'date_format:H:i', 'required_with:album1From'],
            'album2From' => ['nullable', 'date_format:H:i', 'required_with:album2Until'],
            'album2Until' => ['nullable', 'date_format:H:i', 'required_with:album2From'],
            'albumMode' => ['required', 'in:'.implode(',', UpdateClockDisplayAlbumWindowsAction::MODES)],
            'albumDays' => ['array'],
            'albumDays.*' => ['boolean'],
        ]);

        $mask = 0;
        for ($i = 0; $i < 7; $i++) {
            if ($this->albumDays[$i] ?? false) {
                $mask |= 1 << $i;
            }
        }

        try {
            $update->handle(
                $screen->fresh(),
                (int) Tenancy::id(),
                auth()->id(),
                $this->album1From ?: null,
                $this->album1Until ?: null,
                $this->album2From ?: null,
                $this->album2Until ?: null,
                $this->albumMode,
                $mask,
            );
        } catch (InvalidArgumentException $e) {
            session()->flash('screen_flash', __('time.clock_displays.errors.'.$e->getMessage()));

            return;
        }

        session()->flash('screen_flash', __('time.clock_displays.album.saved'));
    }

    public function updatedAlbumPhoto(UploadScreenImageAction $upload): void
    {
        $screen = $this->screen();
        $this->authorize('update', $screen);
        $this->validate([
            'albumPhoto' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:1024'],
        ]);

        try {
            $upload->handle($this->albumPhoto, $screen, (int) Tenancy::id(), auth()->id());
        } catch (InvalidArgumentException $e) {
            session()->flash('screen_flash', __('time.clock_displays.errors.'.$e->getMessage()));
            $this->albumPhoto = null;

            return;
        }

        $this->albumPhoto = null;
        session()->flash('screen_flash', __('time.clock_displays.album.uploaded'));
    }

    public function deleteAlbumImage(int $imageId, DeleteClockDisplayImageAction $delete): void
    {
        $screen = $this->screen();
        $this->authorize('update', $screen);
        $image = ClockDisplayImage::query()->where('screen_id', $screen->id)->find($imageId);
        if ($image === null) {
            return;
        }

        $delete->handle($image, (int) Tenancy::id(), auth()->id());
        session()->flash('screen_flash', __('time.clock_displays.album.deleted'));
    }

    public function render()
    {
        $unit = $this->unit();
        $screen = $unit->screen;
        $pending = $screen === null
            ? null
            : $screen->claims()->activePending()->latest('id')->first();
        $qrUrl = $screen !== null ? ScreenQr::portalUrl($screen) : null;

        return view('livewire.screens.pair', [
            'unit' => $unit,
            'screen' => $screen,
            'pendingClaim' => $pending,
            'qrSvg' => $qrUrl !== null ? QrSvg::svg($qrUrl, 220) : null,
            'albumImages' => $screen === null
                ? collect()
                : $screen->images()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    private function fillFromScreen(Screen $screen): void
    {
        $hm = fn (mixed $t) => $t !== null && $t !== '' ? substr((string) $t, 0, 5) : null;
        $this->displayOnFrom = $hm($screen->display_on_from);
        $this->displayOnUntil = $hm($screen->display_on_until);
        $this->displaySoundEnabled = (bool) $screen->display_sound_enabled;
        $this->album1From = $hm($screen->album1_from);
        $this->album1Until = $hm($screen->album1_until);
        $this->album2From = $hm($screen->album2_from);
        $this->album2Until = $hm($screen->album2_until);
        $this->albumMode = $screen->album_mode ?? 'none';
        $days = (int) ($screen->album_days ?? 31);
        for ($i = 0; $i < 7; $i++) {
            $this->albumDays[$i] = (bool) ($days & (1 << $i));
        }
    }

    private function unit(): Unit
    {
        return Unit::query()->with(['screen.sensePoint'])->findOrFail($this->unitId);
    }

    private function screen(): Screen
    {
        $screen = $this->unit()->screen;
        if ($screen === null) {
            abort(404);
        }

        return $screen;
    }

    private function claim(int $claimId): ScreenClaim
    {
        return ScreenClaim::query()
            ->where('tenant_id', Tenancy::id())
            ->with('screen')
            ->findOrFail($claimId);
    }
}
