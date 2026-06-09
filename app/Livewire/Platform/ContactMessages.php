<?php

namespace App\Livewire\Platform;

use App\Actions\Contact\GetContactMessagesAction;
use App\Actions\Contact\MarkContactMessageAsReadAction;
use App\Actions\Contact\SendContactReplyAction;
use App\Actions\Contact\SendNewOutboundMessageAction;
use App\Models\ContactMessage;
use App\Models\Tenant;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]

class ContactMessages extends Component
{
    use WithPagination;

    public $selectedMessage = null;
    public $reply = '';
    public $filter = 'inbound'; // inbound, outbound
    public $showReplyModal = false;
    public $unreadCount = 0;
    public $selectedMessageIds = [];

    // Compose new message properties
    public $isComposing = false;
    public $newEmail = '';
    public $newName = '';
    public $newSubject = '';
    public $newMessageBody = '';
    public $newMessageTenantId = null;

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
        $tenantId = Tenancy::id() ? (int) Tenancy::id() : null;

        $action = new GetContactMessagesAction();
        $messages = $action->handle($this->filter, 20, $tenantId);
        
        $this->unreadCount = $action->getUnreadCount($tenantId);

        // Load tenants for SuperUser compose form
        $tenants = [];
        if ($this->isComposing) {
            $tenants = Tenant::orderBy('name')->get(['id', 'name']);
        }

        return view('livewire.platform.contact-messages', [
            'messages' => $messages,
            'tenants' => $tenants,
        ]);
    }

    public function selectMessage($messageId)
    {
        $this->isComposing = false;
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
            // Use the tenant_id from the selected message to ensure valid tenant for audit logging
            $tenantId = $this->selectedMessage->tenant_id;

            $action = app(SendContactReplyAction::class);
            $action->handle(
                $this->reply,
                $this->selectedMessage,
                $tenantId,
                auth()->id()
            );

            // Clear state and show success
            $this->reply = '';
            $this->selectedMessage = null;
            $this->closeReplyModal();
            $this->dispatch('reply-sent');
            session()->flash('success', __('contact-messages.reply_sent_success'));
            $this->resetPage();

        } catch (\Exception $e) {
            $this->addError('reply', __('contact-messages.failed_to_send'));
        }
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;
        $this->isComposing = false;
        $this->selectedMessageIds = [];
        $this->resetPage();
    }

    /**
     * Start composing a new outbound message (SuperUser only)
     */
    public function startCompose(): void
    {
        abort_unless(auth()->user()?->is_superuser, 403);

        $this->isComposing = true;
        $this->selectedMessage = null;
        $this->newEmail = '';
        $this->newName = '';
        $this->newSubject = '';
        $this->newMessageBody = '';

        // SuperUsers need to select a tenant
        $this->newMessageTenantId = null;

        $this->resetErrorBag();
    }

    /**
     * Send new outbound message (SuperUser only)
     */
    public function sendNewMessage(): void
    {
        abort_unless(auth()->user()?->is_superuser, 403);

        $this->validate([
            'newEmail' => 'required|email',
            'newName' => 'required|string|max:255',
            'newSubject' => 'required|string|max:255',
            'newMessageBody' => 'required|string|min:1',
            'newMessageTenantId' => 'required|integer|exists:tenants,id',
        ]);

        try {
            $tenantId = (int) $this->newMessageTenantId;

            $action = app(SendNewOutboundMessageAction::class);
            $action->handle(
                recipientEmail: $this->newEmail,
                recipientName: $this->newName,
                subject: $this->newSubject,
                body: $this->newMessageBody,
                tenantId: $tenantId,
                actorUserId: auth()->id(),
            );

            // Reset form and show success
            $this->isComposing = false;
            $this->newEmail = '';
            $this->newName = '';
            $this->newSubject = '';
            $this->newMessageBody = '';

            session()->flash('success', __('contact-messages.new_message_sent_success'));
            $this->resetPage();

        } catch (\Exception $e) {
            $this->addError('newMessage', __('contact-messages.failed_to_send'));
        }
    }

    /**
     * Get IDs of messages currently shown on this page for "select all" functionality
     */
    public function getCurrentPageMessageIds(): array
    {
        $tenantId = Tenancy::id() ? (int) Tenancy::id() : null;
        $action = new GetContactMessagesAction();
        $messages = $action->handle($this->filter, 20, $tenantId);

        return $messages->pluck('id')->toArray();
    }

    /**
     * Toggle select all messages on current page
     */
    public function toggleSelectAll(): void
    {
        $currentIds = $this->getCurrentPageMessageIds();
        $allSelected = count(array_intersect($currentIds, $this->selectedMessageIds)) === count($currentIds);

        if ($allSelected) {
            // Deselect all current page items
            $this->selectedMessageIds = array_diff($this->selectedMessageIds, $currentIds);
        } else {
            // Select all current page items
            $this->selectedMessageIds = array_unique(array_merge($this->selectedMessageIds, $currentIds));
        }
    }

    /**
     * Delete all selected messages
     */
    public function deleteSelected(): void
    {
        if (empty($this->selectedMessageIds)) {
            return;
        }

        $messages = ContactMessage::whereIn('id', $this->selectedMessageIds)->get();

        foreach ($messages as $message) {
            $this->authorize('delete', $message);
            $message->delete();
        }

        // Check if selected message was deleted
        if ($this->selectedMessage && in_array($this->selectedMessage->id, $this->selectedMessageIds)) {
            $this->selectedMessage = null;
        }

        // Reset selection
        $this->selectedMessageIds = [];

        session()->flash('success', __('contact-messages.bulk_deleted_success', ['count' => count($messages)]));
        $this->resetPage();
    }
}