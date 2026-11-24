<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Actions\HelpChat\DismissHelpChatUnansweredQuestionAction;
use App\Actions\HelpChat\SaveHelpChatKbEntryAction;
use App\Actions\Ops\AuthenticateOpsSuperuserAction;
use App\Actions\Ops\EnsureOpsPortalAction;
use App\Actions\Ops\RegisterHardwareDeviceAction;
use App\Actions\Platform\SetPlatformUserPasswordAction;
use App\Data\Ops\RegisterHardwareDeviceData;
use App\Enums\HardwareDeviceCondition;
use App\Enums\HardwareDeviceType;
use App\Http\Requests\Ops\OpsPortalLoginRequest;
use App\Http\Requests\Ops\RegisterHardwareDeviceRequest;
use App\Http\Requests\Platform\SetPlatformUserPasswordRequest;
use App\Models\HardwareDevice;
use App\Models\HelpChatKnowledgeBaseEntry;
use App\Models\HelpChatUnansweredQuestion;
use App\Models\OpsPortal as OpsPortalModel;
use App\Models\User;
use App\Support\Ops\HardwareMac;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.public')]
#[Title('WinProx OPS')]
class OpsPortal extends Component
{
    use AuthorizesRequests;

    public string $token;

    public OpsPortalModel $portal;

    public string $screen = 'home';

    public string $email = '';

    public string $password = '';

    public string $serial_number = '';

    public string $brand = 'Seeed';

    public string $registerType = 'clock_display';

    public string $model = 'Indicator D1';

    public string $sku = '';

    public string $condition = 'new';

    public string $purchase_ref = '';

    public string $notes = '';

    public string $userSearch = '';

    public ?int $passwordUserId = null;

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public string $deviceSearch = '';

    public ?int $answeringQuestionId = null;

    public string $answeringQuestionText = '';

    public string $kbLocale = 'nl';

    public string $kbMatchKey = '';

    public string $kbPatterns = '';

    public string $kbAnswer = '';

    public ?string $flashMessage = null;

    public string $flashType = 'success';

    public bool $homescreenHelpOpen = false;

    public function mount(?string $token = null): void
    {
        if ($token === null || $token === '') {
            $this->portal = app(EnsureOpsPortalAction::class)->handle();
            $this->token = $this->portal->token;

            return;
        }

        $this->token = $token;
        $this->portal = OpsPortalModel::query()
            ->where('token', $token)
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function openHomescreenHelp(): void
    {
        $this->homescreenHelpOpen = true;
    }

    public function closeHomescreenHelp(): void
    {
        $this->homescreenHelpOpen = false;
    }

    public function login(AuthenticateOpsSuperuserAction $action): void
    {
        $this->validate(OpsPortalLoginRequest::ruleSet());

        try {
            $action->handle(
                $this->email,
                $this->password,
                (string) request()->ip(),
            );
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
            if (str_starts_with($message, 'throttle:')) {
                $seconds = (int) substr($message, strlen('throttle:'));
                $this->addError('email', __('auth.errors.throttle', ['seconds' => $seconds]));

                return;
            }

            $this->addError('email', __('ops.errors.'.($message === 'not_superuser' ? 'not_superuser' : 'auth_failed')));

            return;
        }

        $this->password = '';
        $this->screen = 'home';
        session()->regenerate();
    }

    public function logout(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        $this->email = '';
        $this->password = '';
        $this->screen = 'home';
        $this->resetToolState();
    }

    public function openScreen(string $screen): void
    {
        if (! $this->requireAuthedSuperuser()) {
            return;
        }

        if (! in_array($screen, ['home', 'register', 'users', 'help', 'devices'], true)) {
            return;
        }

        $this->flashMessage = null;
        $this->resetToolState();
        $this->screen = $screen;
    }

    public function onBarcodeScanned(string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            return;
        }

        $this->serial_number = $value;
    }

    public function register(RegisterHardwareDeviceAction $action): void
    {
        $user = $this->requireAuthedSuperuser();
        if ($user === null) {
            return;
        }
        $this->authorize('use', $this->portal);
        $this->authorize('create', HardwareDevice::class);

        $rules = RegisterHardwareDeviceRequest::ruleSet();
        $this->validate([
            'serial_number' => $rules['serial_number'],
            'brand' => $rules['brand'],
            'registerType' => $rules['type'],
            'model' => $rules['model'],
            'sku' => $rules['sku'],
            'condition' => $rules['condition'],
            'purchase_ref' => $rules['purchase_ref'],
            'notes' => $rules['notes'],
        ]);

        try {
            $device = $action->handle(
                RegisterHardwareDeviceData::fromValidated([
                    'serial_number' => $this->serial_number,
                    'brand' => $this->brand,
                    'type' => $this->registerType,
                    'model' => $this->model,
                    'sku' => $this->sku,
                    'condition' => $this->condition,
                    'purchase_ref' => $this->purchase_ref,
                    'notes' => $this->notes,
                ]),
                (int) $user->id,
            );
        } catch (InvalidArgumentException $e) {
            $this->addError('serial_number', __('ops.errors.'.$e->getMessage()));

            return;
        }

        $this->flashMessage = __('ops.flash.registered', ['serial' => $device->serial_number]);
        $this->flashType = 'success';
        $this->serial_number = '';
        $this->sku = '';
        $this->purchase_ref = '';
        $this->notes = '';
    }

    public function openPassword(int $userId): void
    {
        if ($this->requireAuthedSuperuser() === null) {
            return;
        }

        $target = User::query()->find($userId);
        if ($target === null) {
            return;
        }
        $this->authorize('setPassword', $target);
        $this->passwordUserId = (int) $target->id;
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';
        $this->resetErrorBag(['newPassword', 'newPasswordConfirmation']);
    }

    public function closePassword(): void
    {
        $this->passwordUserId = null;
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';
        $this->resetErrorBag(['newPassword', 'newPasswordConfirmation']);
    }

    public function savePassword(SetPlatformUserPasswordAction $setPassword): void
    {
        $actor = $this->requireAuthedSuperuser();
        if ($actor === null) {
            return;
        }

        $target = $this->passwordUserId !== null
            ? User::query()->find($this->passwordUserId)
            : null;
        if ($target === null) {
            $this->closePassword();

            return;
        }
        $this->authorize('setPassword', $target);

        $this->validate([
            'newPassword' => SetPlatformUserPasswordRequest::rulesFor()['password'],
            'newPasswordConfirmation' => ['required', 'same:newPassword'],
        ], [
            'newPassword.required' => __('team.errors.password_required'),
            'newPassword.min' => __('team.errors.password_min'),
            'newPasswordConfirmation.required' => __('team.errors.password_confirm_required'),
            'newPasswordConfirmation.same' => __('auth.errors.password_confirmed'),
        ]);

        $setPassword->handle($target, $this->newPassword, (int) $actor->id);
        $name = $target->name;
        $this->closePassword();
        $this->flashMessage = __('platform.users.password.saved', ['name' => $name]);
        $this->flashType = 'success';
    }

    public function openAnswerQuestion(int $id): void
    {
        if ($this->requireAuthedSuperuser() === null) {
            return;
        }

        $question = HelpChatUnansweredQuestion::query()->findOrFail($id);
        $this->authorize('delete', $question);
        $this->authorize('create', HelpChatKnowledgeBaseEntry::class);

        $this->answeringQuestionId = $question->id;
        $this->answeringQuestionText = $question->question;
        $this->kbLocale = $question->locale !== '' ? $question->locale : 'nl';
        $this->kbMatchKey = Str::limit(Str::slug($question->question, '_'), 120, '');
        if ($this->kbMatchKey === '') {
            $this->kbMatchKey = 'vraag_'.$question->id;
        }
        $this->kbPatterns = $question->question;
        $this->kbAnswer = '';
        $this->resetErrorBag(['kbAnswer', 'kbPatterns', 'kbMatchKey', 'kbLocale']);
    }

    public function closeAnswerQuestion(): void
    {
        $this->answeringQuestionId = null;
        $this->answeringQuestionText = '';
        $this->kbLocale = 'nl';
        $this->kbMatchKey = '';
        $this->kbPatterns = '';
        $this->kbAnswer = '';
        $this->resetErrorBag(['kbAnswer', 'kbPatterns', 'kbMatchKey', 'kbLocale']);
    }

    public function saveAnswer(
        SaveHelpChatKbEntryAction $save,
        DismissHelpChatUnansweredQuestionAction $dismiss,
    ): void {
        if ($this->requireAuthedSuperuser() === null) {
            return;
        }
        $this->authorize('create', HelpChatKnowledgeBaseEntry::class);

        if ($this->answeringQuestionId === null) {
            return;
        }

        $question = HelpChatUnansweredQuestion::query()->findOrFail($this->answeringQuestionId);
        $this->authorize('delete', $question);

        $validated = $this->validate([
            'kbLocale' => ['required', 'string', 'max:10'],
            'kbMatchKey' => ['required', 'string', 'max:120'],
            'kbPatterns' => ['required', 'string', 'max:2000'],
            'kbAnswer' => ['required', 'string', 'max:5000'],
        ]);

        $patterns = array_values(array_filter(array_map(
            fn (string $line) => trim($line),
            preg_split('/\r\n|\r|\n/', $validated['kbPatterns']) ?: [],
        )));

        if ($patterns === []) {
            $this->addError('kbPatterns', __('platform.help.patterns_required'));

            return;
        }

        try {
            $save->handle(
                null,
                $validated['kbLocale'],
                $validated['kbMatchKey'],
                $patterns,
                $validated['kbAnswer'],
                true,
            );
        } catch (InvalidArgumentException) {
            $this->addError('kbPatterns', __('platform.help.patterns_required'));

            return;
        }

        $dismiss->handle($this->answeringQuestionId);
        $this->closeAnswerQuestion();
        $this->flashMessage = __('ops.flash.help_answered');
        $this->flashType = 'success';
    }

    public function dismissUnanswered(int $id, DismissHelpChatUnansweredQuestionAction $dismiss): void
    {
        if ($this->requireAuthedSuperuser() === null) {
            return;
        }

        $question = HelpChatUnansweredQuestion::query()->findOrFail($id);
        $this->authorize('delete', $question);
        $dismiss->handle($id);

        if ($this->answeringQuestionId === $id) {
            $this->closeAnswerQuestion();
        }

        $this->flashMessage = __('ops.flash.help_dismissed');
        $this->flashType = 'success';
    }

    public function render()
    {
        $user = Auth::user();
        $authed = $user !== null && $user->is_superuser;

        $users = collect();
        $devices = collect();
        $unanswered = collect();
        $passwordTarget = null;
        $unansweredCount = 0;

        if ($authed) {
            $unansweredCount = HelpChatUnansweredQuestion::query()->count();

            if ($this->screen === 'users') {
                $term = trim($this->userSearch);
                $users = User::query()
                    ->with('tenant')
                    ->when($term !== '', function ($query) use ($term): void {
                        $like = '%'.$term.'%';
                        $query->where(function ($subQuery) use ($like): void {
                            $subQuery->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like)
                                ->orWhereHas('tenant', function ($tenantQuery) use ($like): void {
                                    $tenantQuery->where('name', 'like', $like);
                                });
                        });
                    })
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get();

                if ($this->passwordUserId !== null) {
                    $passwordTarget = User::query()->with('tenant')->find($this->passwordUserId);
                }
            }

            if ($this->screen === 'devices') {
                $devices = $this->searchDevices(trim($this->deviceSearch));
            }

            if ($this->screen === 'help') {
                $unanswered = HelpChatUnansweredQuestion::query()
                    ->with(['tenant', 'user'])
                    ->latest('id')
                    ->limit(50)
                    ->get();
            }
        }

        return view('livewire.public.ops-portal', [
            'authed' => $authed,
            'types' => HardwareDeviceType::cases(),
            'conditions' => HardwareDeviceCondition::cases(),
            'users' => $users,
            'devices' => $devices,
            'unanswered' => $unanswered,
            'unansweredCount' => $unansweredCount,
            'passwordTarget' => $passwordTarget,
        ]);
    }

    private function requireAuthedSuperuser(): ?User
    {
        $user = Auth::user();
        if ($user === null || ! $user->is_superuser) {
            $this->screen = 'home';

            return null;
        }

        return $user;
    }

    private function resetToolState(): void
    {
        $this->closePassword();
        $this->closeAnswerQuestion();
        $this->userSearch = '';
        $this->deviceSearch = '';
        $this->resetErrorBag();
    }

    /**
     * @return \Illuminate\Support\Collection<int, HardwareDevice>
     */
    private function searchDevices(string $term)
    {
        if ($term === '') {
            return HardwareDevice::query()
                ->with('tenant')
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        $like = '%'.$term.'%';
        $mac = HardwareMac::normalize($term);

        return HardwareDevice::query()
            ->with('tenant')
            ->where(function ($query) use ($like, $mac): void {
                $query->where('serial_number', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('model', 'like', $like)
                    ->orWhere('sku', 'like', $like);
                if ($mac !== null && $mac !== '') {
                    $query->orWhere('mac', $mac)
                        ->orWhere('mac', 'like', '%'.$mac.'%');
                }
            })
            ->orderByDesc('id')
            ->limit(30)
            ->get();
    }
}
