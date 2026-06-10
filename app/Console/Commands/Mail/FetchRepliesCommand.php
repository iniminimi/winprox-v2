<?php

namespace App\Console\Commands\Mail;

use App\Actions\Contact\ResolveContactReplyTenantAction;
use App\Actions\Contact\StoreImapContactReplyAction;
use App\Models\ContactMessage;
use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

class FetchRepliesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:fetch-replies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch email replies from IMAP and store them in the database';

    /**
     * Execute the console command.
     */
    public function handle(StoreImapContactReplyAction $storeReply)
    {
        $this->info('Fetching email replies...');

        $cm = new ClientManager();
        
        $client = $cm->make([
            'host' => config('imap.host'),
            'port' => config('imap.port'),
            'encryption' => config('imap.encryption'),
            'username' => config('imap.username'),
            'password' => config('imap.password'),
            'protocol' => config('imap.protocol', 'imap'),
            'authentication' => config('imap.authentication', null),
        ]);

        try {
            $client->connect();
            $folder = $client->getFolder('INBOX');
            
            // Get unseen messages
            $messages = $folder->messages()->unseen()->get();
            
            $processed = 0;
            foreach ($messages as $message) {
                if ($this->processMessage($message, $storeReply, app(ResolveContactReplyTenantAction::class))) {
                    $processed++;
                }
                
                // Mark as seen
                $message->setFlag('Seen');
            }

            $client->disconnect();
            
            $this->info("Processed {$processed} new email replies.");
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Failed to fetch emails: ' . $e->getMessage());
            return 1;
        }
    }

    private function processMessage(
        Message $message,
        StoreImapContactReplyAction $storeReply,
        ResolveContactReplyTenantAction $resolveTenant,
    ): bool {
        $messageId = $message->getMessageId();
        $inReplyTo = $message->getInReplyTo();
        $references = $message->getReferences();

        // Check if message already exists
        if (ContactMessage::where('message_id', $messageId)->exists()) {
            return false;
        }

        // Try to find thread match
        $originalMessage = null;
        if ($inReplyTo) {
            $originalMessage = ContactMessage::where('message_id', $inReplyTo)->first();
        }
        
        if (!$originalMessage && $references) {
            $originalMessage = ContactMessage::whereIn('message_id', explode(' ', trim($references)))->first();
        }

        $tenantId = $resolveTenant->handle($originalMessage);

        $storeReply->handle([
            'message_id' => $messageId,
            'name' => $message->getFrom()[0]->personal ?? $message->getFrom()[0]->mail,
            'email' => $message->getFrom()[0]->mail,
            'subject' => $message->getSubject(),
            'message' => $message->getTextBody() ?? $message->getHTMLBody(),
        ], $tenantId);

        return true;
    }
}
