<?php

namespace App\Livewire\Time;

use App\Actions\Time\DeleteTenantHolidayAgreementAction;
use App\Actions\Time\SaveTenantHolidayAgreementAction;
use App\Enums\HolidayAgreementEffect;
use App\Livewire\Concerns\ProvidesTimeNavAlarmCount;
use App\Models\BelgianPublicHoliday;
use App\Models\Tenant;
use App\Models\TenantHolidayAgreement;
use App\Support\Tenancy;
use App\Support\Time\BelgianPublicHolidayCalendar;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Bedrijfsbrede feestdagenregeling: per feestdag één keuze voor de hele
 * tenant (geen effect / werkdag vervalt / vervangingsdag). De maandnorm
 * gebruikt deze ene set voor alle uitvoerders.
 */
#[Layout('components.layouts.app')]
#[Title('WinProx')]
class HolidaysIndex extends Component
{
    use AuthorizesRequests;
    use ProvidesTimeNavAlarmCount;

    public int $holidayYear;

    /** @var list<array{date: string, code: string, effect: string, replacement_date: string}> */
    public array $agreements = [];

    /** @var array<string, array{effect: string, replacement_date: string}> */
    public array $draftsByDate = [];

    public function mount(): void
    {
        $this->authorize('viewAny', TenantHolidayAgreement::class);
        TimeModuleAccess::assertEnabledForTenantId((int) Tenancy::id());

        $this->holidayYear = (int) now()->year;
        foreach (TenantHolidayAgreement::query()
            ->where('tenant_id', Tenancy::id())
            ->get() as $agreement) {
            $this->draftsByDate[$agreement->holiday_date->toDateString()] = [
                'effect' => $agreement->effect->value,
                'replacement_date' => $agreement->replacement_date?->toDateString() ?? '',
            ];
        }
        $this->presentYear();
    }

    public function setYear(int $year): void
    {
        $this->authorize('viewAny', TenantHolidayAgreement::class);
        $this->rememberDrafts();
        $this->holidayYear = max(
            BelgianPublicHolidayCalendar::FIRST_YEAR,
            min(BelgianPublicHolidayCalendar::LAST_YEAR, $year),
        );
        $this->presentYear();
    }

    public function save(
        SaveTenantHolidayAgreementAction $save,
        DeleteTenantHolidayAgreementAction $delete,
    ): void {
        $this->authorize('create', TenantHolidayAgreement::class);
        $tenant = Tenant::query()->findOrFail(Tenancy::id());

        $this->rememberDrafts();
        try {
            foreach ($this->draftsByDate as $date => $draft) {
                $effect = (string) ($draft['effect'] ?? '');
                $replacement = $effect === HolidayAgreementEffect::Replaced->value
                    && ($draft['replacement_date'] ?? '') !== ''
                    ? (string) $draft['replacement_date']
                    : null;
                if ($effect === '') {
                    $delete->handle($tenant, $date, (int) auth()->id());

                    continue;
                }
                $save->handle(
                    $tenant,
                    $date,
                    HolidayAgreementEffect::from($effect),
                    $replacement,
                    (int) auth()->id(),
                );
            }
        } catch (ValidationException $e) {
            $this->addError('agreements', (string) collect($e->errors())->flatten()->first());

            return;
        }

        $this->resetErrorBag();
        session()->now('time_flash', __('time.holidays.saved'));
    }

    public function render()
    {
        return view('livewire.time.holidays-index', [
            'alarmCount' => $this->timeNavAlarmCount(),
        ]);
    }

    private function rememberDrafts(): void
    {
        foreach ($this->agreements as $row) {
            $date = (string) ($row['date'] ?? '');
            if ($date === '') {
                continue;
            }
            $this->draftsByDate[$date] = [
                'effect' => (string) ($row['effect'] ?? ''),
                'replacement_date' => (string) ($row['replacement_date'] ?? ''),
            ];
        }
    }

    private function presentYear(): void
    {
        $rows = [];
        $holidays = BelgianPublicHoliday::query()
            ->where('year', $this->holidayYear)
            ->orderBy('date')
            ->get();
        foreach ($holidays as $holiday) {
            $date = $holiday->date->toDateString();
            $draft = $this->draftsByDate[$date] ?? ['effect' => '', 'replacement_date' => ''];
            $rows[] = [
                'date' => $date,
                'code' => $holiday->code,
                'effect' => $draft['effect'],
                'replacement_date' => $draft['replacement_date'],
            ];
        }
        $this->agreements = $rows;
    }
}
