<?php

namespace App\Livewire\Platform;

use App\Actions\Contact\GetContactMessagesAction;
use App\Actions\Contact\MarkContactMessageAsReadAction;
use App\Actions\Contact\SendContactReplyAction;
use App\Models\ContactMessage;
use App\Support\Tenancy;
use Livewire\Component;
use Livewire\WithPagination;

class ContactMessages extends Component
{
    use WithPagination;

    public $selectedMessage = null;
    public $reply = '';
    public $filter = 'all'; // all, inbound, outbound
    public $showReplyModal = false;
    public $unreadCount = 0;

    protected $paginationTheme = 'tailwind';

    protected $rules = [
        'reply' => 'required|string|min:1',
    ];

    public function mount()
    {
        $this->authorize('viewAny', ContactMessage::class);
    }

    public function render()
    {
        // Zorg voor een veilige null fallback voor de SuperUser-laag
        $tenantId = Tenancy::id() ? (int) Tenancy::id() : null;

        $action = new GetContactMessagesAction();
        $messages = $action->handle($this->filter, 20, $tenantId);
        
        // Update unread count
        $this->unreadCount = $action->getUnreadCount($tenantId);

        // We binden hem hier direct aan de platform layout om verdere layout-fouten te voorkomen
        return view('livewire.platform.contact-messages', [
            'messages' => $messages,
        ])->layout('layouts.platform');
    }

    public function selectMessage($messageId)
    {
        $this->selectedMessage = ContactMessage::findOrFail($messageId);
        $this->authorize('view', $this->selectedMessage);
        
        $tenantId = Tenancy::id() ? (int) Tenancy::id() : null;

        // Markeer als gelezen indien binnenkomend en ongelezen
        if ($this->selectedMessage->direction === 'inbound' && !$this->selectedMessage->isRead()) {
            $action = new MarkContactMessageAsReadAction();
            $action->handle($this->selectedMessage, $tenantId);
            
            // Refresh unread count
            $getAction = new GetContactMessagesAction();
            $this->unreadCount = $getAction->getUnreadCount($tenantId);
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
            $tenantId = Tenancy::id() ? (int) Tenancy::id() : null;
            
            $action = new SendContactReplyAction();
            $action->handle(
                $this->reply,
                $this->selectedMessage,
                $tenantId,
                auth()->id()
            );

            $this->closeReplyModal();
            $this->dispatch('reply-sent');
            
            // Refresh de lijst en herstel selectie naar het bijgewerkte bericht
            $this->selectedMessage = ContactMessage::findOrFail($this->selectedMessage->id);
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
}