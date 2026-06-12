<?php

namespace App\Livewire\Platform;

use App\Actions\Manual\ReadManualScreenshotCaptureStatusAction;
use App\Actions\Manual\StartManualScreenshotCaptureAction;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class ManualScreenshots extends Component
{
    use AuthorizesRequests;

    public ?string $flashMessage = null;

    public string $flashType = 'success';

    public function mount(): void
    {
        $this->authorize('captureManualScreenshots', User::class);
    }

    public function startCapture(StartManualScreenshotCaptureAction $start): void
    {
        $this->authorize('captureManualScreenshots', User::class);

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        try {
            $start->handle((int) $user->id);
            $this->flashType = 'success';
            $this->flashMessage = __('platform.manual_screenshots.queued');
        } catch (\InvalidArgumentException) {
            $this->flashType = 'error';
            $this->flashMessage = __('platform.manual_screenshots.not_configured');
        } catch (\RuntimeException $e) {
            $this->flashType = 'error';
            $this->flashMessage = $e->getMessage() === 'manual_capture_already_running'
                ? __('platform.manual_screenshots.already_running')
                : __('platform.manual_screenshots.failed');
        }
    }

    public function render(ReadManualScreenshotCaptureStatusAction $readStatus)
    {
        return view('livewire.platform.manual-screenshots', [
            'status' => $readStatus->handle(),
            'isConfigured' => $this->isConfigured(),
        ]);
    }

    private function isConfigured(): bool
    {
        return config('manual_capture.email') !== null
            && config('manual_capture.email') !== ''
            && config('manual_capture.password') !== null
            && config('manual_capture.password') !== '';
    }
}
