<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Actions\Units\DeactivateUnitCheckListAction;
use App\Actions\Units\SaveUnitCheckListAction;
use App\Data\Units\SaveUnitCheckListData;
use App\Enums\UnitCheckResult;
use App\Http\Requests\Units\SaveUnitCheckListRequest;
use App\Models\Location;
use App\Models\UnitCheck;
use App\Models\UnitCheckList;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class UnitChecksIndex extends Component
{
    use WithPagination;

    #[Url(as: 'result')]
    public string $resultFilter = 'all';

    #[Url(as: 'location')]
    public ?int $locationFilter = null;

    public bool $showListModal = false;

    public ?int $editingListId = null;

    public string $listName = '';

    public string $listItemsText = '';

    public bool $listIsActive = true;

    public function mount(): void
    {
        $this->authorize('viewAny', UnitCheck::class);

        if (! in_array($this->resultFilter, ['all', ...UnitCheckResult::values()], true)) {
            $this->resultFilter = 'all';
        }
    }

    public function updatedResultFilter(): void
    {
        $this->resetPage();
    }

    public function updatedLocationFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateList(): void
    {
        $this->authorize('create', UnitCheckList::class);
        $this->editingListId = null;
        $this->listName = '';
        $this->listItemsText = '';
        $this->listIsActive = true;
        $this->showListModal = true;
        $this->resetErrorBag();
    }

    public function openEditList(int $listId): void
    {
        $list = UnitCheckList::query()->with('items')->findOrFail($listId);
        $this->authorize('update', $list);
        $this->editingListId = (int) $list->id;
        $this->listName = $list->name;
        $this->listItemsText = $list->items->pluck('label')->implode("\n");
        $this->listIsActive = (bool) $list->is_active;
        $this->showListModal = true;
        $this->resetErrorBag();
    }

    public function closeListModal(): void
    {
        $this->showListModal = false;
        $this->editingListId = null;
        $this->listName = '';
        $this->listItemsText = '';
        $this->listIsActive = true;
        $this->resetErrorBag();
    }

    public function saveList(SaveUnitCheckListAction $saveList): void
    {
        $payload = [
            'name' => trim($this->listName),
            'items' => $this->listItemsText,
            'is_active' => $this->listIsActive,
        ];

        $validator = Validator::make(
            $payload,
            SaveUnitCheckListRequest::staticRules(),
            SaveUnitCheckListRequest::validationMessages(),
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field === 'items' ? 'listItemsText' : ($field === 'name' ? 'listName' : $field), $message);
                }
            }

            return;
        }

        try {
            if ($this->editingListId === null) {
                $this->authorize('create', UnitCheckList::class);
                $saveList->handle(
                    SaveUnitCheckListData::fromValidated($validator->validated()),
                    Tenancy::id(),
                    null,
                    (int) auth()->id(),
                );
            } else {
                $list = UnitCheckList::query()->findOrFail($this->editingListId);
                $this->authorize('update', $list);
                $saveList->handle(
                    SaveUnitCheckListData::fromValidated($validator->validated()),
                    Tenancy::id(),
                    $list,
                    (int) auth()->id(),
                );
            }
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field === 'items' ? 'listItemsText' : $field, $message);
                }
            }

            return;
        }

        $this->closeListModal();
    }

    public function deactivateList(int $listId, DeactivateUnitCheckListAction $deactivate): void
    {
        $list = UnitCheckList::query()->findOrFail($listId);
        $this->authorize('delete', $list);
        $deactivate->handle($list, (int) auth()->id());
    }

    public function render(): View
    {
        $query = UnitCheck::query()
            ->with(['unit', 'location', 'worker', 'team'])
            ->orderByDesc('checked_at')
            ->orderByDesc('id');

        if ($this->resultFilter !== 'all') {
            $query->where('result', $this->resultFilter);
        }

        if ($this->locationFilter !== null) {
            $query->where('location_id', $this->locationFilter);
        }

        return view('livewire.pages.unit-checks-index', [
            'checks' => $query->paginate(25),
            'locations' => Location::query()->orderBy('name')->get(['id', 'name', 'address']),
            'lists' => UnitCheckList::query()
                ->withCount('items')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
