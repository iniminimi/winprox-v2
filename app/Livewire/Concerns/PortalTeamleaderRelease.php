<?php

namespace App\Livewire\Concerns;

use App\Actions\Portal\TeamleaderReleaseWorkerIconAction;
use App\Models\InternalTeam;
use App\Models\Worker;
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

    public ?int $release_worker_id = null;

    public string $release_teamleader_icon_slug = '';

    public function toggleReleasePanel(): void
    {
        $this->showReleasePanel = ! $this->showReleasePanel;
        if (! $this->showReleasePanel) {
            $this->resetReleaseForm();
        }
    }

    /** @return \Illuminate\Support\Collection<int, Worker> */
    public function blockedReleaseCandidates()
    {
        $team = $this->portalReleaseTeam();
        $teamleader = $this->portalTeamleaderWorker();

        if ($team === null) {
            return collect();
        }

        return Worker::query()
            ->where('internal_team_id', $team->id)
            ->where('is_active', true)
            ->whereNotNull('field_icon_locked_at')
            ->when($teamleader !== null, fn ($query) => $query->where('id', '!=', $teamleader->id))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
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
            'release_worker_id' => ['required', 'integer'],
        ], [
            'release_teamleader_icon_slug.required' => __('portal.worker.errors.icon_required'),
            'release_worker_id.required' => __('portal.teamleader.errors.colleague_required'),
        ]);

        $expected = trim((string) $teamleader->field_icon_slug);
        if ($expected === '' || $this->release_teamleader_icon_slug !== $expected) {
            $this->addError('release_teamleader_icon_slug', __('portal.teamleader.errors.icon_wrong'));

            return;
        }

        $target = Worker::query()
            ->where('internal_team_id', $team->id)
            ->whereKey($this->release_worker_id)
            ->first();

        if ($target === null || $target->field_icon_locked_at === null) {
            $this->addError('release_worker_id', __('portal.teamleader.errors.colleague_not_found'));

            return;
        }

        if ((int) $target->id === (int) $teamleader->id) {
            $this->addError('release_worker_id', __('portal.teamleader.errors.cannot_release'));

            return;
        }

        try {
            $releaseIcon->handle($team, $teamleader, $target);
        } catch (\InvalidArgumentException) {
            $this->addError('release_worker_id', __('portal.teamleader.errors.cannot_release'));

            return;
        }

        $this->resetReleaseForm();
        $this->showReleasePanel = false;
        $this->portalReleaseFlash(__('portal.teamleader.released_ok', ['name' => $target->displayName()]));
    }

    protected function resetReleaseForm(): void
    {
        $this->release_worker_id = null;
        $this->release_teamleader_icon_slug = '';
        $this->resetErrorBag(['release_teamleader_icon_slug', 'release_worker_id', 'release_identify']);
    }

    /** Ingelogde teamleader op dit portaal (unit of team). */
    abstract protected function portalTeamleaderWorker(): ?Worker;

    /** Team waarvoor vrijgave geldt. */
    abstract protected function portalReleaseTeam(): ?InternalTeam;

    abstract protected function portalReleaseFlash(string $message): void;
}
