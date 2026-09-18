<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Actions\Communication\ImportUnitCheckListTranslationsAction;
use App\Actions\Units\CopyUnitCheckListFromStarterAction;
use App\Actions\Units\DeactivateUnitCheckListAction;
use App\Actions\Units\DeleteUnitCheckListAction;
use App\Actions\Units\SaveUnitCheckListAction;
use App\Data\Units\SaveUnitCheckListData;
use App\Http\Requests\Units\SaveUnitCheckListRequest;
use App\Models\InternalTeam;
use App\Models\UnitCheckList;
use App\Support\Tenancy;
use App\Support\Translation\LocaleSupport;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class CheckListsIndex extends Component
{
    use AuthorizesRequests;

    public bool $showCheckListModal = false;

    public ?int $editingCheckListId = null;

    public string $checkListName = '';

    public string $checkListItemsText = '';

    public bool $checkListIsActive = true;

    public ?int $checkListTeamId = null;

    public string $checkListPreviewLocale = '';

    public string $checkListTranslationName = '';

    public string $checkListTranslationItemsText = '';

    public function mount(): void
    {
        $this->authorize('viewAny', UnitCheckList::class);
    }

    public function openCreateCheckList(): void
    {
        $this->authorize('create', UnitCheckList::class);
        $this->editingCheckListId = null;
        $this->checkListName = '';
        $this->checkListItemsText = '';
        $this->checkListIsActive = true;
        $this->checkListTeamId = null;
        $this->checkListPreviewLocale = '';
        $this->checkListTranslationName = '';
        $this->checkListTranslationItemsText = '';
        $this->showCheckListModal = true;
        $this->resetErrorBag();
    }

    public function openEditCheckList(int $listId): void
    {
        $list = UnitCheckList::query()->with(['items', 'translations'])->findOrFail($listId);
        $this->authorize('update', $list);
        $this->editingCheckListId = (int) $list->id;
        $this->checkListName = $list->name;
        $this->checkListItemsText = $list->items->pluck('label')->implode("\n");
        $this->checkListIsActive = (bool) $list->is_active;
        $this->checkListTeamId = $list->internal_team_id;
        $this->checkListPreviewLocale = $this->defaultTranslationLocaleForCheckList($list);
        $this->hydrateCheckListTranslationInput($list);
        $this->showCheckListModal = true;
        $this->resetErrorBag();
    }

    public function updatedCheckListPreviewLocale(): void
    {
        if ($this->editingCheckListId === null) {
            $this->checkListTranslationName = '';
            $this->checkListTranslationItemsText = '';

            return;
        }

        $list = UnitCheckList::query()
            ->with(['items', 'translations'])
            ->find($this->editingCheckListId);

        $this->hydrateCheckListTranslationInput($list);
    }

    public function saveCheckListTranslationOverride(ImportUnitCheckListTranslationsAction $importTranslations): void
    {
        if ($this->editingCheckListId === null) {
            return;
        }

        $list = UnitCheckList::query()
            ->with(['items', 'translations'])
            ->findOrFail($this->editingCheckListId);
        $this->authorize('update', $list);

        if (! $list->is_active) {
            $this->addError('checkListTranslationName', __('unit_checks.lists.errors.translation_requires_active'));

            return;
        }

        $validated = $this->validate([
            'checkListPreviewLocale' => ['required', 'string', 'max:5'],
            'checkListTranslationName' => ['required', 'string', 'max:255'],
            'checkListTranslationItemsText' => ['required', 'string'],
        ], [
            'checkListTranslationName.required' => __('unit_checks.lists.errors.name_required'),
            'checkListTranslationItemsText.required' => __('unit_checks.lists.errors.items_required'),
        ]);

        $locale = LocaleSupport::normalize((string) $validated['checkListPreviewLocale']);
        if ($locale === $list->normalizedOriginalLanguage()) {
            $this->addError('checkListTranslationName', __('issues.errors.translation_same_as_source'));

            return;
        }

        $name = trim((string) $validated['checkListTranslationName']);
        $rawItems = preg_split("/\r\n|\n|\r/", (string) $validated['checkListTranslationItemsText']) ?: [];
        $items = [];
        foreach ($rawItems as $item) {
            $label = trim((string) $item);
            if ($label !== '') {
                $items[] = $label;
            }
        }

        if ($name === '' || $items === []) {
            $this->addError('checkListTranslationName', __('issues.errors.translation_import_invalid'));

            return;
        }

        try {
            $importTranslations->handle([
                [
                    'unit_check_list_id' => $list->id,
                    'locale' => $locale,
                    'name' => $name,
                    'items' => $items,
                ],
            ], (int) auth()->id());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('checkListTranslationName', (string) $message);
                }
            }

            return;
        }

        $this->hydrateCheckListTranslationInput($list->fresh(['items', 'translations']));
        session()->flash('success', __('unit_checks.lists.flash.translation_saved'));
    }

    public function closeCheckListModal(): void
    {
        $this->showCheckListModal = false;
        $this->editingCheckListId = null;
        $this->checkListName = '';
        $this->checkListItemsText = '';
        $this->checkListIsActive = true;
        $this->checkListTeamId = null;
        $this->checkListPreviewLocale = '';
        $this->checkListTranslationName = '';
        $this->checkListTranslationItemsText = '';
        $this->resetErrorBag();
    }

    public function saveCheckList(SaveUnitCheckListAction $saveList): void
    {
        $tenantId = (int) Tenancy::id();
        $payload = [
            'name' => trim($this->checkListName),
            'items' => $this->checkListItemsText,
            'is_active' => $this->checkListIsActive,
            'internal_team_id' => $this->checkListTeamId,
        ];

        if ($this->editingCheckListId === null) {
            $payload['original_language'] = LocaleSupport::normalize(app()->getLocale());
        }

        $validator = Validator::make(
            $payload,
            SaveUnitCheckListRequest::staticRules($tenantId),
            SaveUnitCheckListRequest::validationMessages(),
        );

        if ($validator->fails()) {
            $this->mapCheckListErrors($validator->errors()->messages());

            return;
        }

        try {
            if ($this->editingCheckListId === null) {
                $this->authorize('create', UnitCheckList::class);
                $saveList->handle(
                    SaveUnitCheckListData::fromValidated($validator->validated()),
                    $tenantId,
                    null,
                    (int) auth()->id(),
                );
            } else {
                $list = UnitCheckList::query()->findOrFail($this->editingCheckListId);
                $this->authorize('update', $list);
                $saveList->handle(
                    SaveUnitCheckListData::fromValidated($validator->validated()),
                    $tenantId,
                    $list,
                    (int) auth()->id(),
                );
            }
        } catch (ValidationException $exception) {
            $this->mapCheckListErrors($exception->errors());

            return;
        }

        $this->closeCheckListModal();
    }

    public function copyCheckListFromStarter(string $starterKey, CopyUnitCheckListFromStarterAction $copy): void
    {
        $this->authorize('create', UnitCheckList::class);

        try {
            $copy->handle(
                $starterKey,
                (int) Tenancy::id(),
                null,
                (int) auth()->id(),
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('checkListName', (string) $message);
                }
            }
        }
    }

    public function deactivateCheckList(int $listId, DeactivateUnitCheckListAction $deactivate): void
    {
        $list = UnitCheckList::query()->findOrFail($listId);
        $this->authorize('delete', $list);
        $deactivate->handle($list, (int) auth()->id());
    }

    public function deleteCheckList(int $listId, DeleteUnitCheckListAction $delete): void
    {
        $list = UnitCheckList::query()->findOrFail($listId);
        $this->authorize('delete', $list);

        try {
            $delete->handle($list, (int) auth()->id());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('checkListName', (string) $message);
                }
            }

            return;
        }

        if ($this->editingCheckListId === $listId) {
            $this->closeCheckListModal();
        }
    }

    public function render(): View
    {
        $checkListTranslationLocales = config('locales.labels', []);
        if ($this->showCheckListModal && $this->editingCheckListId !== null) {
            $editingCheckList = UnitCheckList::query()->find($this->editingCheckListId);

            if ($editingCheckList !== null) {
                $sourceLocale = $editingCheckList->normalizedOriginalLanguage();
                $checkListTranslationLocales = array_filter(
                    $checkListTranslationLocales,
                    fn (string $label, string $code): bool => $code !== $sourceLocale,
                    ARRAY_FILTER_USE_BOTH,
                );
            }
        }

        return view('livewire.pages.check-lists-index', [
            'checkListTranslationLocales' => $checkListTranslationLocales,
            'checkLists' => UnitCheckList::query()
                ->with(['internalTeam.translations', 'translations'])
                ->withCount(['items', 'units'])
                ->orderBy('name')
                ->get(),
            'checkListTeams' => InternalTeam::query()
                ->where('is_active', true)
                ->with('translations')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'original_language']),
            'checkListStarters' => config('unit_check_starters', []),
        ]);
    }

    /**
     * @param  array<string, list<string>>  $messages
     */
    private function mapCheckListErrors(array $messages): void
    {
        $map = [
            'items' => 'checkListItemsText',
            'name' => 'checkListName',
            'internal_team_id' => 'checkListTeamId',
        ];

        foreach ($messages as $field => $fieldMessages) {
            foreach ($fieldMessages as $message) {
                $this->addError($map[$field] ?? $field, $message);
            }
        }
    }

    private function hydrateCheckListTranslationInput(?UnitCheckList $list): void
    {
        if ($list === null) {
            $this->checkListTranslationName = '';
            $this->checkListTranslationItemsText = '';

            return;
        }

        $locale = LocaleSupport::normalize($this->checkListPreviewLocale);
        if ($locale === '' || $locale === $list->normalizedOriginalLanguage()) {
            $locale = $this->defaultTranslationLocaleForCheckList($list);
            $this->checkListPreviewLocale = $locale;
        }

        $translation = $list->translations
            ->first(fn ($row) => $row->locale === $locale);

        $this->checkListTranslationName = (string) ($translation?->name ?? '');
        $translatedItems = is_array($translation?->items) ? $translation->items : [];
        $this->checkListTranslationItemsText = collect($translatedItems)
            ->map(static fn ($item) => trim((string) $item))
            ->filter(static fn (string $item) => $item !== '')
            ->implode("\n");
    }

    private function defaultTranslationLocaleForCheckList(UnitCheckList $list): string
    {
        $targets = LocaleSupport::targetLocalesForSource($list->normalizedOriginalLanguage());
        $preferred = LocaleSupport::normalize(auth()->user()?->locale ?? app()->getLocale());

        if (in_array($preferred, $targets, true)) {
            return $preferred;
        }

        return $targets[0] ?? $preferred;
    }
}
