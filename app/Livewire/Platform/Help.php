<?php

namespace App\Livewire\Platform;

use App\Models\HelpChatKnowledgeBaseEntry;
use App\Models\HelpChatUnansweredQuestion;
use App\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Help extends Component
{
    public bool $showKbModal = false;

    public ?int $editingKbId = null;

    public string $kbLocale = '*';

    public string $kbMatchKey = '';

    public string $kbPatterns = '';

    public string $kbAnswer = '';

    public bool $kbIsActive = true;

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_superuser, 403);
    }

    public function openCreateKb(): void
    {
        $this->resetKbForm();
        $this->editingKbId = null;
        $this->showKbModal = true;
    }

    public function openEditKb(int $id): void
    {
        $entry = HelpChatKnowledgeBaseEntry::query()->findOrFail($id);
        $this->editingKbId = $entry->id;
        $this->kbLocale = $entry->locale;
        $this->kbMatchKey = $entry->match_key;
        $this->kbPatterns = implode("\n", $entry->patterns ?? []);
        $this->kbAnswer = $entry->answer;
        $this->kbIsActive = $entry->is_active;
        $this->resetErrorBag();
        $this->showKbModal = true;
    }

    public function closeKbModal(): void
    {
        $this->showKbModal = false;
        $this->resetKbForm();
    }

    public function saveKb(): void
    {
        $validated = $this->validate([
            'kbLocale' => ['required', 'string', 'max:10'],
            'kbMatchKey' => ['required', 'string', 'max:120'],
            'kbPatterns' => ['required', 'string', 'max:2000'],
            'kbAnswer' => ['required', 'string', 'max:5000'],
            'kbIsActive' => ['boolean'],
        ]);

        $patterns = array_values(array_filter(array_map(
            fn (string $line) => trim($line),
            preg_split('/\r\n|\r|\n/', $validated['kbPatterns']) ?: [],
        )));

        if ($patterns === []) {
            $this->addError('kbPatterns', __('platform.help.patterns_required'));

            return;
        }

        $payload = [
            'locale' => $validated['kbLocale'],
            'match_key' => $validated['kbMatchKey'],
            'patterns' => $patterns,
            'answer' => $validated['kbAnswer'],
            'is_active' => $validated['kbIsActive'],
        ];

        if ($this->editingKbId !== null) {
            HelpChatKnowledgeBaseEntry::query()->whereKey($this->editingKbId)->update($payload);
        } else {
            HelpChatKnowledgeBaseEntry::create($payload);
        }

        $this->closeKbModal();
    }

    public function deleteKb(int $id): void
    {
        HelpChatKnowledgeBaseEntry::query()->whereKey($id)->delete();
    }

    public function dismissUnanswered(int $id): void
    {
        HelpChatUnansweredQuestion::query()->whereKey($id)->delete();
    }

    private function resetKbForm(): void
    {
        $this->editingKbId = null;
        $this->kbLocale = '*';
        $this->kbMatchKey = '';
        $this->kbPatterns = '';
        $this->kbAnswer = '';
        $this->kbIsActive = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.platform.help', [
            'unanswered' => HelpChatUnansweredQuestion::query()
                ->with(['tenant', 'user'])
                ->latest('id')
                ->limit(100)
                ->get(),
            'kbEntries' => HelpChatKnowledgeBaseEntry::query()
                ->orderBy('locale')
                ->orderBy('match_key')
                ->get(),
            'tenants' => Tenant::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
