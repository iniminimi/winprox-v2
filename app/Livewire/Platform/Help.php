<?php

namespace App\Livewire\Platform;

use App\Actions\HelpChat\DeleteHelpChatKbEntryAction;
use App\Actions\HelpChat\DismissHelpChatUnansweredQuestionAction;
use App\Actions\HelpChat\EnsureHelpChatKbEntryTranslationSlotsAction;
use App\Actions\HelpChat\SaveHelpChatKbEntryAction;
use App\Models\HelpChatKnowledgeBaseEntry;
use App\Models\HelpChatKnowledgeBaseEntryTranslation;
use App\Models\HelpChatUnansweredQuestion;
use App\Models\Tenant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Help extends Component
{
    use AuthorizesRequests;

    public bool $showKbModal = false;

    public ?int $editingKbId = null;

    public string $kbLocale = 'nl';

    public string $kbMatchKey = '';

    public string $kbPatterns = '';

    public string $kbAnswer = '';

    public bool $kbIsActive = true;

    public ?int $answeringQuestionId = null;

    public ?string $answeringQuestionText = null;

    /** @var list<HelpChatKnowledgeBaseEntryTranslation> */
    public array $kbTranslations = [];

    public function mount(): void
    {
        $this->authorize('viewAny', HelpChatKnowledgeBaseEntry::class);
    }

    public function openCreateKb(): void
    {
        $this->resetKbForm();
        $this->editingKbId = null;
        $this->showKbModal = true;
    }

    public function openAnswerQuestion(int $id): void
    {
        $question = HelpChatUnansweredQuestion::query()->findOrFail($id);
        $this->authorize('delete', $question);
        $this->authorize('create', HelpChatKnowledgeBaseEntry::class);

        $this->resetKbForm();
        $this->answeringQuestionId = $question->id;
        $this->answeringQuestionText = $question->question;
        $this->kbLocale = $question->locale !== '' ? $question->locale : 'nl';
        $this->kbMatchKey = Str::limit(Str::slug($question->question, '_'), 120, '');
        if ($this->kbMatchKey === '') {
            $this->kbMatchKey = 'vraag_'.$question->id;
        }
        $this->kbPatterns = $question->question;
        $this->showKbModal = true;
    }

    public function openEditKb(int $id, EnsureHelpChatKbEntryTranslationSlotsAction $ensureSlots): void
    {
        $entry = HelpChatKnowledgeBaseEntry::query()->with('translations')->findOrFail($id);
        $this->authorize('update', $entry);
        $ensureSlots->handle($entry);
        $entry->load('translations');

        $this->editingKbId = $entry->id;
        $this->kbLocale = $entry->original_language;
        $this->kbMatchKey = $entry->match_key;
        $this->kbPatterns = implode("\n", $entry->patterns ?? []);
        $this->kbAnswer = $entry->answer;
        $this->kbIsActive = $entry->is_active;
        $this->kbTranslations = $entry->translations->sortBy('locale')->values()->all();
        $this->answeringQuestionId = null;
        $this->answeringQuestionText = null;
        $this->resetErrorBag();
        $this->showKbModal = true;
    }

    public function closeKbModal(): void
    {
        $this->showKbModal = false;
        $this->resetKbForm();
    }

    public function saveKb(SaveHelpChatKbEntryAction $save): void
    {
        if ($this->editingKbId !== null) {
            $entry = HelpChatKnowledgeBaseEntry::query()->findOrFail($this->editingKbId);
            $this->authorize('update', $entry);
        } else {
            $this->authorize('create', HelpChatKnowledgeBaseEntry::class);
        }

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

        try {
            $save->handle(
                $this->editingKbId,
                $validated['kbLocale'],
                $validated['kbMatchKey'],
                $patterns,
                $validated['kbAnswer'],
                $validated['kbIsActive'],
            );
        } catch (\InvalidArgumentException) {
            $this->addError('kbPatterns', __('platform.help.patterns_required'));

            return;
        }

        if ($this->answeringQuestionId !== null) {
            app(DismissHelpChatUnansweredQuestionAction::class)->handle($this->answeringQuestionId);
        }

        $this->closeKbModal();
    }

    public function deleteKb(int $id, DeleteHelpChatKbEntryAction $delete): void
    {
        $entry = HelpChatKnowledgeBaseEntry::query()->findOrFail($id);
        $this->authorize('delete', $entry);

        $delete->handle($id);
    }

    public function dismissUnanswered(int $id, DismissHelpChatUnansweredQuestionAction $dismiss): void
    {
        $question = HelpChatUnansweredQuestion::query()->findOrFail($id);
        $this->authorize('delete', $question);

        $dismiss->handle($id);
    }

    private function resetKbForm(): void
    {
        $this->editingKbId = null;
        $this->kbLocale = 'nl';
        $this->kbMatchKey = '';
        $this->kbPatterns = '';
        $this->kbAnswer = '';
        $this->kbIsActive = true;
        $this->answeringQuestionId = null;
        $this->answeringQuestionText = null;
        $this->kbTranslations = [];
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
                ->orderBy('original_language')
                ->orderBy('match_key')
                ->get(),
            'tenants' => Tenant::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
