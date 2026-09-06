<?php

namespace App\Livewire\Public;

use App\Actions\Time\AcknowledgeTimeRosterViewAction;
use App\Actions\Time\ListOpenTimeRosterAction;
use App\Actions\Time\ResolveTimeRosterQrTokenAction;
use App\Http\Requests\Time\AcknowledgeTimeRosterViewRequest;
use App\Livewire\Concerns\SwitchesPortalUiTheme;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Portal\WorkerIcon;
use App\Support\Portal\WorkerIconGuard;
use App\Support\Portal\WorkerVerification;
use App\Support\Qr\InvalidQrResponse;
use App\Support\ResolveAppLocale;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Time-aanwezigheidslijst via aparte QR: alleen een aangemelde uitvoerder,
 * na expliciete audit-bevestiging (brandweer/evacuatie).
 */
#[Layout('components.layouts.public')]
#[Title('WinProx')]
class TimeRosterPortal extends Component
{
    use SwitchesPortalUiTheme;

    public string $token;
    public int $tenantId;
    public string $locale = 'nl';
    public ?string $inactiveReasonKey = null;

    public string $first_name = '';
    public string $last_name = '';
    public string $sign_in_icon_slug = '';
    public bool $acknowledged = false;
    public bool $listUnlocked = false;

    public function mount(string $token): void
    {
        $resolved = app(ResolveTimeRosterQrTokenAction::class)->handle($token);
        if ($resolved === null) {
            InvalidQrResponse::abort();
        }

        $tenant = $resolved['tenant'];
        $this->token = $token;
        $this->tenantId = (int) $tenant->id;
        $this->inactiveReasonKey = $resolved['inactiveReasonKey'];

        Tenancy::actAs($this->tenantId);
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
        Cookie::queue(ResolveAppLocale::COOKIE_NAME, $locale, ResolveAppLocale::COOKIE_MINUTES);
        $this->locale = $locale;
        app()->setLocale($this->locale);
    }

    public function identifyWorker(): void
    {
        if ($this->inactiveReasonKey !== null) {
            return;
        }

        $this->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
        ], [
            'first_name.required' => __('portal.worker.errors.name_required'),
            'last_name.required' => __('portal.worker.errors.name_required'),
        ]);

        $identity = WorkerDeviceSession::resolveIdentityForTenant(
            $this->tenantId,
            $this->first_name,
            $this->last_name,
        );

        if ($identity['status'] === 'ambiguous') {
            $this->addError('identify', __('portal.worker.errors.identify_ambiguous'));

            return;
        }

        $worker = $identity['worker'] ?? null;
        if ($identity['status'] !== 'found' || $worker === null) {
            $this->addError('identify', __('portal.worker.errors.identify_unknown'));

            return;
        }

        WorkerDeviceSession::bindRememberedWorkerForTenant($worker);
        $this->sign_in_icon_slug = '';
        $this->resetErrorBag(['identify', 'sign_in_icon_slug']);
    }

    public function signInWithIcon(): void
    {
        if ($this->inactiveReasonKey !== null) {
            return;
        }

        $deviceWorker = $this->rememberedWorker();
        if ($deviceWorker === null) {
            return;
        }

        $team = $deviceWorker->team;
        if ($team === null) {
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
            $this->addError(
                'sign_in_icon_slug',
                WorkerIconGuard::isBlocked($team)
                    ? __('portal.worker.errors.blocked')
                    : __('portal.worker.errors.icon_wrong'),
            );

            return;
        }

        $this->sign_in_icon_slug = '';
        $this->acknowledged = false;
        $this->listUnlocked = false;
    }

    public function acknowledgeView(AcknowledgeTimeRosterViewAction $acknowledge): void
    {
        $worker = $this->verifiedWorker();
        if ($worker === null || $this->inactiveReasonKey !== null) {
            return;
        }

        $this->validate(
            AcknowledgeTimeRosterViewRequest::rulesFor(),
            AcknowledgeTimeRosterViewRequest::messagesFor(),
        );

        try {
            $acknowledge->handle($worker, $this->tenantId);
        } catch (InvalidArgumentException) {
            $this->addError('acknowledged', __('time.roster.ack_required'));

            return;
        }

        $this->listUnlocked = true;
    }

    public function signInAsDifferentWorker(): void
    {
        $verified = $this->verifiedWorker();
        $team = $verified?->team ?? $this->rememberedWorker()?->team;

        WorkerDeviceSession::revokeDeviceSessionFromRequest($team);
        if ($team !== null) {
            WorkerIconGuard::clearSessionForTeam((int) $team->id);
            WorkerVerification::clearForTeam((int) $team->id);
        }

        $this->reset(['first_name', 'last_name', 'sign_in_icon_slug', 'acknowledged', 'listUnlocked']);
        $this->resetErrorBag();
    }

    public function render(ListOpenTimeRosterAction $listRoster)
    {
        $verified = $this->verifiedWorker();
        $roster = null;
        if ($this->listUnlocked && $verified !== null && $this->inactiveReasonKey === null) {
            $roster = $listRoster->handle($this->tenantId);
        }

        return view('livewire.public.time-roster-portal', [
            'tenant' => Tenant::query()->find($this->tenantId),
            'rememberedWorker' => $this->rememberedWorker(),
            'verifiedWorker' => $verified,
            'showIdentify' => $this->rememberedWorker() === null,
            'showIcon' => $this->rememberedWorker() !== null && $verified === null,
            'showAck' => $verified !== null && ! $this->listUnlocked,
            'roster' => $roster,
        ]);
    }

    private function rememberedWorker(): ?Worker
    {
        $fromCookie = WorkerDeviceSession::workerFromDeviceCookie();
        if ($fromCookie !== null && (int) $fromCookie->tenant_id === $this->tenantId && $fromCookie->is_active) {
            return $fromCookie;
        }

        return Worker::query()
            ->where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->get()
            ->first(function (Worker $worker) {
                $team = $worker->team;
                if ($team === null) {
                    return false;
                }

                $remembered = WorkerDeviceSession::rememberedWorkerOnTeam($team);

                return $remembered !== null && (int) $remembered->id === (int) $worker->id;
            });
    }

    private function verifiedWorker(): ?Worker
    {
        $deviceWorker = $this->rememberedWorker();
        if ($deviceWorker === null) {
            return null;
        }

        $team = $deviceWorker->team;
        if ($team === null) {
            return null;
        }

        $verified = WorkerVerification::verifiedWorker($team);
        if ($verified === null || (int) $verified->id !== (int) $deviceWorker->id) {
            return null;
        }

        return $verified;
    }

    private function syncLocaleFromRequest(): void
    {
        $this->locale = app()->getLocale();
    }
}
