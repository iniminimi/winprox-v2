<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Support\Marketing\PromoBounceMessageParser;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

class ProcessPromoMailboxBouncesAction
{
    public function __construct(
        private MarkPromoCampaignEmailBouncedAction $markBounced,
    ) {}

    /**
     * @return array{
     *     scanned: int,
     *     bounce_messages: int,
     *     emails_found: int,
     *     marked: int,
     *     blocked: int,
     *     dry_run: bool
     * }
     */
    public function handle(bool $unseenOnly = true, ?int $limit = null, bool $dryRun = false): array
    {
        $config = config('imap.promo');
        $username = trim((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');
        $host = trim((string) ($config['host'] ?? ''));

        if ($host === '' || $username === '' || $password === '') {
            throw new RuntimeException('Promo IMAP is not configured (imap.promo).');
        }

        $cm = new ClientManager;
        $client = $cm->make([
            'host' => $host,
            'port' => (int) ($config['port'] ?? 993),
            'encryption' => $config['encryption'] ?? 'ssl',
            'username' => $username,
            'password' => $password,
            'protocol' => $config['protocol'] ?? 'imap',
            'authentication' => $config['authentication'] ?? null,
        ]);

        $scanned = 0;
        $bounceMessages = 0;
        $emailsFound = 0;
        $marked = 0;
        $blocked = 0;

        try {
            $client->connect();
            $folder = $client->getFolder('INBOX');
            $query = $unseenOnly
                ? $folder->messages()->unseen()
                : $folder->messages()->all();

            if ($limit !== null && $limit > 0) {
                $query->limit($limit);
            }

            $messages = $query->get();

            foreach ($messages as $message) {
                $scanned++;
                $result = $this->processMessage($message, $dryRun);
                if (! $result['is_bounce']) {
                    continue;
                }

                $bounceMessages++;
                $emailsFound += $result['emails_found'];
                $marked += $result['marked'];
                $blocked += $result['blocked'];

                if (! $dryRun) {
                    $message->setFlag('Seen');
                }
            }

            $client->disconnect();
        } catch (Throwable $exception) {
            try {
                $client->disconnect();
            } catch (Throwable) {
                // ignore disconnect errors
            }

            throw $exception;
        }

        return [
            'scanned' => $scanned,
            'bounce_messages' => $bounceMessages,
            'emails_found' => $emailsFound,
            'marked' => $marked,
            'blocked' => $blocked,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * @return array{is_bounce: bool, emails_found: int, marked: int, blocked: int}
     */
    private function processMessage(Message $message, bool $dryRun): array
    {
        $subject = (string) $message->getSubject();
        $from = '';
        $fromCollection = $message->getFrom();
        if (is_iterable($fromCollection)) {
            foreach ($fromCollection as $address) {
                $from = (string) ($address->mail ?? $address->personal ?? '');
                break;
            }
        }

        if (! PromoBounceMessageParser::looksLikeBounce($subject, $from)) {
            return ['is_bounce' => false, 'emails_found' => 0, 'marked' => 0, 'blocked' => 0];
        }

        $body = (string) ($message->getTextBody() ?: $message->getHTMLBody() ?: '');
        $emails = PromoBounceMessageParser::extractRecipientEmails($subject, $body);

        $marked = 0;
        $blocked = 0;

        foreach ($emails as $email) {
            if ($dryRun) {
                $blocked++;

                continue;
            }

            $result = $this->markBounced->handle($email, 'Undelivered Mail Returned to Sender');
            $marked += $result['marked'];
            if ($result['blocked']) {
                $blocked++;
            }
        }

        return [
            'is_bounce' => true,
            'emails_found' => count($emails),
            'marked' => $marked,
            'blocked' => $blocked,
        ];
    }
}
