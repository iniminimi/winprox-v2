<?php

namespace App\Livewire\Platform;

use App\Actions\Contact\SendContactReplyAction;
use App\Models\ContactMessage;
use Livewire\Component;
use Livewire\WithPagination;

class ContactMessages extends Component
{
    use WithPagination;

    public $selectedMessage = null;
    public $reply = '';
    public $filter = 'all'; // all, inbound, outbound
    public $showReplyModal = false;

    protected $paginationTheme = 'tailwind';

    protected $rules = [
        'reply' => 'required|string|min:1',
    ];

    public function mount()
    {
        if (!auth()->user()->is_superuser) {
            abort(403);
        }
    }

    public function render()
    {
        $query = ContactMessage::query();

        if ($this->filter !== 'all') {
            $query->where('direction', $this->filter);
        }

        $messages = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('livewire.platform.contact-messages', [
            'messages' => $messages,
        ]);
    }

    public function selectMessage($messageId)
    {
        $this->selectedMessage = ContactMessage::findOrFail($messageId);
        
        // Mark as read if inbound and unread
        if ($this->selectedMessage->direction === 'inbound' && !$this->selectedMessage->isRead()) {
            $this->selectedMessage->markAsRead();
        }

        $this->dispatch('message-selected');
    }

    public function openReplyModal()
    {
        if (!$this->selectedMessage || $this->selectedMessage->direction !== 'inbound') {
            return;
        }

        $this->reply = '';
        $this->showReplyModal = true;
    }

    public function closeReplyModal()
    {
        $this->showReplyModal = false;
        $this->reply = '';
    }

    public function sendReply()
    {
        $this->validate();

        if (!$this->selectedMessage || $this->selectedMessage->direction !== 'inbound') {
            return;
        }

        try {
            $action = new SendContactReplyAction();
            $action->handle(
                $this->reply,
                $this->selectedMessage,
                tenant()->id,
                auth()->id()
            );

            $this->closeReplyModal();
            $this->dispatch('reply-sent');
            
            // Refresh the messages list
            $this->resetPage();

        } catch (\Exception $e) {
            $this->addError('reply', 'Failed to send reply: ' . $e->getMessage());
        }
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    public function getUnreadCountProperty()
    {
        return ContactMessage::inbound()->unread()->count();
    }

    public function hydrate()
    {
        // Refresh unread count when component hydrates
        $this->unreadCount;
    }
}
