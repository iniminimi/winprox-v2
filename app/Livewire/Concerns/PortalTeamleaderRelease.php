<?php

namespace App\Livewire\Concerns;

use App\Actions\Portal\TeamleaderReleaseWorkerIconAction;
use App\Models\InternalTeam;
use App\Models\Worker;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Portal\WorkerIcon;
use Illuminate\Validation\Rule;

/**
 * Gedeeld gedrag voor unit- en team-QR: teamleader mag collega-icoon vrijgeven.
 *
 * @phpstan-require-extends \Livewire\Component
 */
trait PortalTeamleaderRelease
{
    public bool $showReleasePanel = false;

    public string $release_first_name = '';

    public string $release_last_name = '';

    public string $release_teamleader_icon_slug = '';

    public function toggleReleasePanel(): void
    {
        $this->showReleasePanel = ! $this->showReleasePanel;
        if (! $this->showReleasePanel) {
            $this->resetReleaseForm();
        }
    }

    public function releaseColleagueIcon(TeamleaderReleaseWorkerIconAction $releaseIcon): void
    {
        $teamleader = $this->portalTeamleaderWorker();
        $team = $this->portalReleaseTeam();

        if ($teamleader === null || $team === null) {
            return;
        }

        $this->validate([
            'release_teamleader_icon_slug' => ['required', 'string', Rule::in(WorkerIcon::SLUGS)],
            'release_first_name' => ['required', 'string', 'max:120'],
            'release_last_name' => ['required', 'string', 'max:120'],
        ], [
            'release_teamleader_icon_slug.required' => __('portal.worker.errors.icon_required'),
            'release_first_name.required' => __('portal.worker.errors.name_required'),
            'release_last_name.required' => __('portal.worker.errors.name_required'),
        ]);

        $expected = trim((string) $teamleader->field_icon_slug);
        if ($expected === '' || $this->release_teamleader_icon_slug !== $expected) {
            $this->addError('release_teamleader_icon_slug', __('portal.teamleader.errors.icon_wrong'));

            return;
        }

        $identity = WorkerDeviceSession::resolveIdentityOnTeam(
            $team,
            $this->release_first_name,
            $this->release_last_name,
        );

        if ($identity['status'] === 'ambiguous') {
            $this->addError('release_identify', __('portal.worker.errors.identify_ambiguous'));

            return;
        }

        if ($identity['status'] !== 'found') {
            $this->addError('release_identify', __('portal.teamleader.errors.colleague_not_found'));

            return;
        }

        $target = $identity['worker'] ?? null;
        if (! $target instanceof Worker) {
            $this->addError('release_identify', __('portal.teamleader.errors.colleague_not_found'));

            return;
        }

        try {
            $releaseIcon->handle($team, $teamleader, $target);
        } catch (\InvalidArgumentException) {
            $this->addError('release_identify', __('portal.teamleader.errors.cannot_release'));

            return;
        }

        $this->resetReleaseForm();
        $this->showReleasePanel = false;
        $this->portalReleaseFlash(__('portal.teamleader.released_ok', ['name' => $target->displayName()]));
    }

    protected function resetReleaseForm(): void
    {
        $this->release_first_name = '';
        $this->release_last_name = '';
        $this->release_teamleader_icon_slug = '';
        $this->resetErrorBag(['release_teamleader_icon_slug', 'release_first_name', 'release_last_name', 'release_identify']);
    }

    /** Ingelogde teamleader op dit portaal (unit of team). */
    abstract protected function portalTeamleaderWorker(): ?Worker;

    /** Team waarvoor vrijgave geldt. */
    abstract protected function portalReleaseTeam(): ?InternalTeam;

    abstract protected function portalReleaseFlash(string $message): void;
}
