<?php

namespace App\Livewire\Components;

use App\Actions\HelpChat\EscalateHelpChatAnswerAction;
use App\Actions\HelpChat\ProcessHelpChatMessageAction;
use Livewire\Component;

class HelpChat extends Component
{
    /** @var list<array{role: string, content: string}> */
    public array $messages = [];

    public string $draft = '';

    public ?string $lastQuestion = null;

    public function mount(): void
    {
        $this->messages[] = [
            'role' => 'assistant',
            'content' => __('help.welcome'),
        ];
    }

    public function send(ProcessHelpChatMessageAction $process): void
    {
        $text = trim($this->draft);

        if ($text === '') {
            return;
        }

        $this->messages[] = ['role' => 'user', 'content' => $text];
        $this->lastQuestion = $text;
        $this->draft = '';

        $reply = $process->handle(auth()->user(), $text);
        $this->messages[] = [
            'role' => $reply['role'],
            'content' => $reply['content'],
        ];
    }

    public function escalateToHelpdesk(EscalateHelpChatAnswerAction $escalate): void
    {
        if ($this->lastQuestion === null) {
            return;
        }

        $escalate->escalate(auth()->user(), $this->lastQuestion, collect($this->messages)->last()['content'] ?? null);

        $this->messages[] = [
            'role' => 'assistant',
            'content' => __('help.escalated'),
        ];
    }

    public function render()
    {
        return view('livewire.components.help-chat');
    }
}
