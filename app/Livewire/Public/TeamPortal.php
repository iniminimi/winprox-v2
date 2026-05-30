<?php

namespace App\Livewire\Public;

use App\Livewire\Concerns\PortalTeamleaderRelease;
use App\Models\InternalTeam;
use App\Models\Worker;
use App\Support\Portal\PortalAccess;
use App\Support\Portal\TeamPortalData;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Portal\WorkerIcon;
use App\Support\Portal\WorkerIconGuard;
use App\Support\Portal\WorkerVerification;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Team-QR portaal: READ-ONLY takenoverzicht na identificatie + icoonbevestiging.
 * Afhandelen kan alleen via de unit-QR ter plaatse (hint getoond). Lege teams
 * krijgen open registratie (onboarding: naam + persoonlijk icoon).
 */
#[Layout('components.layouts.public')]
#[Title('WinProx')]
class TeamPortal extends Component
{
    use PortalTeamleaderRelease;

    public string $token;
    public int $teamId;
    public int $tenantId;
    public string $teamName = '';

    public string $locale = 'nl';
    public ?string $inactiveReasonKey = null;

    public string $first_name = '';
    public string $last_name = '';
    public string $sign_in_icon_slug = '';

    public bool $showRegisterForm = false;
    public string $selected_icon_slug = '';

    public string $flashMessage = '';

    public function mount(string $token): void
    {
        $team = InternalTeam::withoutGlobalScope('tenant')
            ->where('field_qr_token', $token)
            ->first();

        abort_unless($team, 404);

        $this->token = $token;
        $this->teamId = $team->id;
        $this->tenantId = $team->tenant_id;
        $this->teamName = $team->name;

        Tenancy::actAs($this->tenantId);

        $this->inactiveReasonKey = PortalAccess::teamPortalInactiveReasonKey($team);

        // Team-QR wist de bezoekverificatie bij elke scan (verse icoonbevestiging).
        WorkerVerification::clearForTeam($this->teamId);

        $this->syncLocaleFromRequest();
    }

    public function booted(): void
    {
        Tenancy::actAs($this->tenantId);
        app()->setLocale($this->locale);
    }

    public function switchLocale(string $locale): void
    {
        if (! in_array($locale, config('locales.supported', []), true)) {
            return;
        }

        session(['locale' => $locale]);
        Cookie::queue('locale', $locale, 60 * 24 * 365);
        $this->locale = $locale;
        app()->setLocale($locale);
    }

    public function identifyWorker(): void
    {
        $team = $this->activeTeam();
        if ($team === null) {
            return;
        }

        $this->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
        ], [
            'first_name.required' => __('portal.worker.errors.name_required'),
            'last_name.required' => __('portal.worker.errors.name_required'),
        ]);

        $identity = WorkerDeviceSession::resolveIdentityOnTeam($team, $this->first_name, $this->last_name);

        if ($identity['status'] === 'ambiguous') {
            $this->addError('identify', __('portal.worker.errors.identify_ambiguous'));

            return;
        }

        if ($identity['status'] === 'not_found') {
            $this->addError('identify', __('portal.worker.errors.identify_unknown'));

            return;
        }

        if ($identity['status'] === 'claimable') {
            $this->showRegisterForm = true;
            $this->selected_icon_slug = '';
            $this->flashMessage = __('portal.team.claimable_hint');

            return;
        }

        $worker = $identity['worker'] ?? null;
        if ($worker === null) {
            $this->addError('identify', __('portal.worker.errors.identify_unknown'));

            return;
        }

        WorkerDeviceSession::bindRememberedWorker($team, $worker);
        $this->showRegisterForm = false;
        $this->sign_in_icon_slug = '';
        $this->resetErrorBag(['identify', 'sign_in_icon_slug']);
    }

    public function signInAsDifferentWorker(): void
    {
        $team = $this->activeTeam();
        if ($team === null) {
            return;
        }

        WorkerDeviceSession::revokeDeviceSessionFromRequest($team);
        WorkerIconGuard::clearSessionForTeam((int) $team->id);
        WorkerVerification::clearForTeam((int) $team->id);

        $this->reset(['first_name', 'last_name', 'sign_in_icon_slug', 'selected_icon_slug', 'showRegisterForm']);
        $this->resetErrorBag(['identify', 'sign_in_icon_slug', 'selected_icon_slug']);
    }

    public function signInWithIcon(): void
    {
        $team = $this->activeTeam();
        if ($team === null) {
            return;
        }

        $deviceWorker = WorkerDeviceSession::rememberedWorkerOnTeam($team);
        if ($deviceWorker === null) {
            return;
        }

        if (WorkerIconGuard::isBlocked($team)) {
            $this->sign_in_icon_slug = '';
            $this->addError('sign_in_icon_slug', __('portal.worker.errors.blocked'));

            return;
        }

        $this->validate(
            ['sign_in_icon_slug' => ['required', 'string', Rule::in(WorkerIcon::SLUGS)]],
            ['sign_in_icon_slug.required' => __('portal.worker.errors.icon_required'), 'sign_in_icon_slug.in' => __('portal.worker.errors.icon_required')],
        );

        $worker = WorkerVerification::confirmIconForWorker($team, $deviceWorker, $this->sign_in_icon_slug);

        if ($worker === null) {
            WorkerIconGuard::recordFailedAttempt($team);
            $this->sign_in_icon_slug = '';
            $this->addFailedSignInError($team);

            return;
        }

        WorkerDeviceSession::bindRememberedWorker($team, $worker);
        $this->sign_in_icon_slug = '';
        $this->flashMessage = __('portal.worker.signed_in');
    }

    public function showRegister(): void
    {
        $team = $this->activeTeam();
        if ($team === null || ! TeamPortalData::allowsOpenRegistration($team)) {
            return;
        }

        $this->showRegisterForm = true;
        $this->selected_icon_slug = '';
        $this->resetErrorBag(['selected_icon_slug', 'identify']);
    }

    public function cancelRegistration(): void
    {
        $this->showRegisterForm = false;
        $this->selected_icon_slug = '';
        $this->resetErrorBag(['selected_icon_slug', 'identify']);
    }

    public function completeOnboarding(): void
    {
        $team = $this->activeTeam();
        if ($team === null) {
            return;
        }

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'selected_icon_slug' => ['required', 'string', Rule::in(WorkerIcon::SLUGS)],
        ], [
            'first_name.required' => __('portal.worker.errors.name_required'),
            'last_name.required' => __('portal.worker.errors.name_required'),
            'selected_icon_slug.required' => __('portal.worker.errors.icon_required'),
            'selected_icon_slug.in' => __('portal.worker.errors.icon_required'),
        ]);

        // Buiten open registratie mag enkel een bestaande "claimable" worker zijn icoon claimen.
        if (! TeamPortalData::allowsOpenRegistration($team)) {
            $identity = WorkerDeviceSession::resolveIdentityOnTeam($team, $validated['first_name'], $validated['last_name']);
            if ($identity['status'] !== 'claimable') {
                $this->addError('identify', __('portal.worker.errors.identify_unknown'));

                return;
            }
        }

        $result = WorkerDeviceSession::registerWorkerForTeam(
            $team,
            $validated['first_name'],
            $validated['last_name'],
            $validated['selected_icon_slug'],
        );

        $worker = $result['worker'];
        WorkerDeviceSession::bindRememberedWorker($team, $worker);
        WorkerVerification::markVerified($team, $worker);

        $this->reset(['first_name', 'last_name', 'selected_icon_slug', 'showRegisterForm', 'sign_in_icon_slug']);
        $this->flashMessage = __('portal.team.onboarding_done');
    }

    public function render()
    {
        $team = $this->activeTeam();
        app()->setLocale($this->locale);

        $verifiedWorker = $team !== null ? WorkerVerification::verifiedWorker($team) : null;
        $canAct = $verifiedWorker !== null;

        $hasSignInWorkers = $team !== null && WorkerIcon::workersOnTeamWithIcon($team)->isNotEmpty();
        $allowOpenRegistration = $team !== null && TeamPortalData::allowsOpenRegistration($team);
        $registerOnly = $team !== null && ! $hasSignInWorkers && $allowOpenRegistration;

        $deviceWorker = (! $canAct && $team !== null) ? WorkerDeviceSession::rememberedWorkerOnTeam($team) : null;
        $iconBlocked = (! $canAct && $hasSignInWorkers && $team !== null) ? WorkerIconGuard::isBlocked($team) : false;

        $showRegisterForm = $this->showRegisterForm && ! $registerOnly;

        $showIdentify = $team !== null && ! $canAct && $hasSignInWorkers
            && $deviceWorker === null && ! $showRegisterForm && ! $registerOnly && ! $iconBlocked;
        $showVerify = $team !== null && ! $canAct && $hasSignInWorkers
            && $deviceWorker !== null && ! $showRegisterForm && ! $registerOnly && ! $iconBlocked;

        $tasks = $canAct && $team !== null ? TeamPortalData::openTasksForTeam($team) : collect();

        return view('livewire.public.team-portal', [
            'team' => $team,
            'canAct' => $canAct,
            'verifiedWorker' => $verifiedWorker,
            'hasSignInWorkers' => $hasSignInWorkers,
            'allowOpenRegistration' => $allowOpenRegistration,
            'registerOnly' => $registerOnly,
            'showRegisterForm' => $showRegisterForm,
            'showIdentify' => $showIdentify,
            'showVerify' => $showVerify,
            'iconBlocked' => $iconBlocked,
            'deviceWorker' => $deviceWorker,
            'remainingAttempts' => $team !== null ? WorkerIconGuard::remainingAttempts($team) : WorkerIconGuard::MAX_FAILED_ATTEMPTS,
            'tasks' => $tasks,
        ]);
    }

    private function activeTeam(): ?InternalTeam
    {
        if ($this->inactiveReasonKey !== null) {
            return null;
        }

        return InternalTeam::find($this->teamId);
    }

    private function addFailedSignInError(InternalTeam $team): void
    {
        if (WorkerIconGuard::isBlocked($team)) {
            $this->addError('sign_in_icon_slug', __('portal.worker.errors.blocked'));

            return;
        }

        $message = WorkerIconGuard::remainingAttempts($team) === 1
            ? __('portal.worker.errors.icon_wrong_one_left')
            : __('portal.worker.errors.icon_wrong');

        $this->addError('sign_in_icon_slug', $message);
    }

    private function syncLocaleFromRequest(): void
    {
        $supported = config('locales.supported', []);
        $lang = request()->query('lang');

        if (is_string($lang) && in_array($lang, $supported, true)) {
            session(['locale' => $lang]);
            $this->locale = $lang;
        } else {
            $this->locale = app()->getLocale();
        }

        app()->setLocale($this->locale);
    }

    protected function portalTeamleaderWorker(): ?Worker
    {
        $team = $this->activeTeam();
        if ($team === null) {
            return null;
        }

        $worker = WorkerVerification::verifiedWorker($team);

        return ($worker !== null && $worker->is_teamleader) ? $worker : null;
    }

    protected function portalReleaseTeam(): ?InternalTeam
    {
        return $this->activeTeam();
    }

    protected function portalReleaseFlash(string $message): void
    {
        $this->flashMessage = $message;
    }
}
