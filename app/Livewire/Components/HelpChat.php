<?php

namespace App\Livewire\Components;

use App\Actions\HelpChat\EscalateHelpChatAnswerAction;
use App\Actions\HelpChat\ProcessHelpChatMessageAction;
use Livewire\Component;

class HelpChat extends Component
{
    /** @var list<array{id: int, role: string, content: string}> */
    public array $messages = [];

    public string $draft = '';

    public ?string $escalationQuestion = null;

    public ?string $escalationReply = null;

    public int $messageSeq = 0;


    public function send(ProcessHelpChatMessageAction $process): void
    {
        $text = trim($this->draft);

        if ($text === '') {
            return;
        }

        $this->draft = '';

        $reply = $process->handle(auth()->user(), $text);

        // Newest exchange at the top of the panel (under the form).
        array_unshift(
            $this->messages,
            $this->makeMessage('user', $text),
            $this->makeMessage($reply['role'], $reply['content']),
        );

        if (($reply['escalated'] ?? false) === true) {
            $this->escalationQuestion = $text;
            $this->escalationReply = $reply['content'];
        } else {
            $this->escalationQuestion = null;
            $this->escalationReply = null;
        }
    }

    public function escalateToHelpdesk(EscalateHelpChatAnswerAction $escalate): void
    {
        if ($this->escalationQuestion === null) {
            return;
        }

        $escalate->escalate(
            auth()->user(),
            $this->escalationQuestion,
            $this->escalationReply,
        );

        $this->escalationQuestion = null;
        $this->escalationReply = null;

        array_unshift(
            $this->messages,
            $this->makeMessage('assistant', __('help.escalated')),
        );
    }

    /**
     * @return array{id: int, role: string, content: string}
     */
    protected function makeMessage(string $role, string $content): array
    {
        $this->messageSeq++;

        return [
            'id' => $this->messageSeq,
            'role' => $role,
            'content' => $content,
        ];
    }

    public function render()
    {
        return view('livewire.components.help-chat');
    }
}


