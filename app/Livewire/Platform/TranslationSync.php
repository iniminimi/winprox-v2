<?php

namespace App\Livewire\Platform;

use App\Actions\Communication\ReadTranslationSyncStatusAction;
use App\Actions\Communication\StartTranslationSyncAction;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class TranslationSync extends Component
{
    use AuthorizesRequests;

    public ?string $flashMessage = null;

    public string $flashType = 'success';

    public function mount(): void
    {
        $this->authorize('runTranslationSync', User::class);
    }

    public function start(StartTranslationSyncAction $start): void
    {
        $this->authorize('runTranslationSync', User::class);

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        try {
            $start->handle((int) $user->id);
            $this->flashType = 'success';
            $this->flashMessage = __('platform.translation_sync.queued');
        } catch (\InvalidArgumentException) {
            $this->flashType = 'error';
            $this->flashMessage = $this->notConfiguredMessage();
        } catch (\RuntimeException $e) {
            $this->flashType = 'error';
            $this->flashMessage = $e->getMessage() === 'translation_sync_already_running'
                ? __('platform.translation_sync.already_running')
                : __('platform.translation_sync.failed');
        }
    }

    public function render(ReadTranslationSyncStatusAction $readStatus)
    {
        return view('livewire.platform.translation-sync', [
            'status' => $readStatus->handle(),
            'isConfigured' => $this->isConfigured(),
            'useSyncQueue' => config('queue.default') === 'sync',
        ]);
    }

    private function isConfigured(): bool
    {
        if (! config('translation_sync.enabled', false)) {
            return false;
        }

        foreach (['ssh_host', 'ssh_user', 'remote_path'] as $key) {
            if ((string) config("translation_sync.{$key}") === '') {
                return false;
            }
        }

        return true;
    }

    private function notConfiguredMessage(): string
    {
        if (! config('translation_sync.enabled', false)) {
            return __('platform.translation_sync.not_enabled');
        }

        return __('platform.translation_sync.not_configured');
    }
}
