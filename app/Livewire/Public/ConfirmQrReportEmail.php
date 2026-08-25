<?php

namespace App\Livewire\Public;

use App\Actions\Public\ConfirmQrReportEmailHoldAction;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.public')]
#[Title('WinProx')]
class ConfirmQrReportEmail extends Component
{
    public string $status = 'pending';

    public string $message = '';

    public ?string $unitPortalUrl = null;

    public function mount(string $token, ConfirmQrReportEmailHoldAction $confirm): void
    {
        try {
            $issue = $confirm->handle($token);
            $this->status = 'ok';
            $this->message = __('portal.report.verify_email_ok');
            $qrToken = $issue->unit?->qr_token;
            if (is_string($qrToken) && $qrToken !== '') {
                $this->unitPortalUrl = route('public.unit-portal', ['token' => $qrToken]);
            }
        } catch (ValidationException $e) {
            $this->status = 'error';
            $this->message = collect($e->errors())->flatten()->first()
                ?? __('portal.report.verify_email_invalid');
        }
    }

    public function render()
    {
        return view('livewire.public.confirm-qr-report-email');
    }
}
