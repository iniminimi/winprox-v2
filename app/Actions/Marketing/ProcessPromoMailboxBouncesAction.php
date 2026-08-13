<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Support\Marketing\PromoBounceMessageParser;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Query\WhereQuery;

class ProcessPromoMailboxBouncesAction
{
    public const DEFAULT_SINCE_DAYS = 30;

    public const DEFAULT_MANUAL_LIMIT = 250;

    public function __construct(
        private MarkPromoCampaignEmailBouncedAction $markBounced,
    ) {}

    /**
     * @return array{
     *     scanned: int,
     *     bounce_messages: int,
     *     emails_found: int,
     *     removed: int,
     *     blocked: int,
     *     dry_run: bool
     * }
     */
    public function handle(
        bool $unseenOnly = true,
        ?int $limit = null,
        bool $dryRun = false,
        ?int $sinceDays = null,
    ): array {
        @set_time_limit(120);

        $config = config('imap.promo');
        $username = trim((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');
        $host = trim((string) ($config['host'] ?? ''));

        if ($host === '' || $username === '' || $password === '') {
            throw new RuntimeException('Promo IMAP is not configured (imap.promo).');
        }

        if (! $unseenOnly && ($sinceDays === null || $sinceDays < 1)) {
            $sinceDays = self::DEFAULT_SINCE_DAYS;
        }

        $cm = new ClientManager;
        $client = $cm->make([
            'host' => $host,
            'port' => (int) ($config['port'] ?? 993),
            'encryption' => $config['encryption'] ?? 'ssl',
            'validate_cert' => true,
            'timeout' => 20,
            'username' => $username,
            'password' => $password,
            'protocol' => $config['protocol'] ?? 'imap',
            'authentication' => $config['authentication'] ?? null,
        ]);

        $scanned = 0;
        $bounceMessages = 0;
        $emailsFound = 0;
        $removed = 0;
        $blocked = 0;

        try {
            $client->connect();
            $folder = $client->getFolder('INBOX');
            if (! $folder instanceof Folder) {
                throw new RuntimeException('Promo IMAP INBOX folder not found.');
            }

            $messages = $this->fetchCandidateMessages($folder, $unseenOnly, $limit, $sinceDays);

            foreach ($messages as $message) {
                $scanned++;
                $result = $this->processMessage($message, $dryRun);
                if (! $result['is_bounce']) {
                    continue;
                }

                $bounceMessages++;
                $emailsFound += $result['emails_found'];
                $removed += $result['removed'];
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
            'removed' => $removed,
            'blocked' => $blocked,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * @return iterable<int, Message>
     */
    private function fetchCandidateMessages(
        Folder $folder,
        bool $unseenOnly,
        ?int $limit,
        ?int $sinceDays,
    ): iterable {
        $since = $sinceDays !== null && $sinceDays > 0
            ? now()->subDays($sinceDays)
            : null;

        $unseenUids = [];
        foreach ($this->searchUidList($folder, function (WhereQuery $query) use ($since): void {
            $query->unseen();
            if ($since !== null) {
                $query->since($since);
            }
        }) as $uid) {
            $unseenUids[$uid] = $uid;
        }

        $bounceUids = [];
        if (! $unseenOnly) {
            foreach ($this->bounceSearchConfigurators($since) as $configure) {
                foreach ($this->searchUidList($folder, $configure) as $uid) {
                    $bounceUids[$uid] = $uid;
                }
            }
        }

        $uids = $this->prioritizeUids(array_values($bounceUids), array_values($unseenUids), $limit);

        if ($uids === []) {
            return [];
        }

        $query = $folder->messages()->softFail(true);

        return $query->curate_messages(Collection::make($uids));
    }

    /**
     * @return list<callable(WhereQuery): void>
     */
    private function bounceSearchConfigurators(mixed $since): array
    {
        $withSince = function (WhereQuery $query) use ($since): void {
            if ($since !== null) {
                $query->since($since);
            }
        };

        return [
            function (WhereQuery $query) use ($withSince): void {
                $withSince($query);
                $query->from('MAILER-DAEMON');
            },
            function (WhereQuery $query) use ($withSince): void {
                $withSince($query);
                $query->from('postmaster');
            },
            function (WhereQuery $query) use ($withSince): void {
                $withSince($query);
                $query->subject('Undelivered');
            },
            function (WhereQuery $query) use ($withSince): void {
                $withSince($query);
                $query->subject('Undeliverable');
            },
            function (WhereQuery $query) use ($withSince): void {
                $withSince($query);
                $query->subject('Delivery Status Notification');
            },
            function (WhereQuery $query) use ($withSince): void {
                $withSince($query);
                $query->subject('Mail delivery failed');
            },
        ];
    }

    /**
     * @param  callable(WhereQuery): void  $configure
     * @return list<int>
     */
    private function searchUidList(Folder $folder, callable $configure): array
    {
        try {
            $query = $folder->messages()->softFail(true);
            $configure($query);

            return array_values(array_filter(
                array_map('intval', $query->search()->all()),
                fn (int $uid): bool => $uid > 0,
            ));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Keep bounce matches even when many unseen replies would fill the limit.
     *
     * @param  list<int>  $bounceUids
     * @param  list<int>  $unseenUids
     * @return list<int>
     */
    private function prioritizeUids(array $bounceUids, array $unseenUids, ?int $limit): array
    {
        $sortDesc = function (array $uids): array {
            $uids = array_values(array_unique($uids));
            rsort($uids, SORT_NUMERIC);

            return $uids;
        };

        $bounceUids = $sortDesc($bounceUids);
        $unseenUids = $sortDesc($unseenUids);

        if ($limit === null || $limit < 1) {
            return array_values(array_unique([...$bounceUids, ...$unseenUids]));
        }

        if (count($bounceUids) >= $limit) {
            return array_slice($bounceUids, 0, $limit);
        }

        $selected = $bounceUids;
        $selectedLookup = array_fill_keys($selected, true);
        foreach ($unseenUids as $uid) {
            if (isset($selectedLookup[$uid])) {
                continue;
            }
            $selected[] = $uid;
            $selectedLookup[$uid] = true;
            if (count($selected) >= $limit) {
                break;
            }
        }

        return $selected;
    }

    /**
     * @return array{is_bounce: bool, emails_found: int, removed: int, blocked: int}
     */
    private function processMessage(Message $message, bool $dryRun): array
    {
        $subject = (string) $message->getSubject();
        $from = $this->messageFrom($message);

        if (! PromoBounceMessageParser::looksLikeBounce($subject, $from)) {
            return ['is_bounce' => false, 'emails_found' => 0, 'removed' => 0, 'blocked' => 0];
        }

        $body = PromoBounceMessageParser::haystackFromParts(
            headers: (string) ($message->getHeader()?->raw ?? ''),
            textBody: (string) $message->getTextBody(),
            htmlBody: (string) $message->getHTMLBody(),
            rawBody: $this->rawBody($message),
            attachmentBodies: $this->attachmentBodies($message),
        );
        $emails = PromoBounceMessageParser::extractRecipientEmails($subject, $body);

        $removed = 0;
        $blocked = 0;

        foreach ($emails as $email) {
            if ($dryRun) {
                $blocked++;

                continue;
            }

            $result = $this->markBounced->handle($email, 'Undelivered Mail Returned to Sender');
            $removed += $result['removed'];
            if ($result['blocked']) {
                $blocked++;
            }
        }

        return [
            'is_bounce' => true,
            'emails_found' => count($emails),
            'removed' => $removed,
            'blocked' => $blocked,
        ];
    }

    private function messageFrom(Message $message): string
    {
        $fromCollection = $message->getFrom();
        if (is_iterable($fromCollection)) {
            foreach ($fromCollection as $address) {
                $mail = trim((string) ($address->mail ?? $address->personal ?? ''));
                if ($mail !== '') {
                    return $mail;
                }
            }
        }

        return trim((string) $fromCollection);
    }

    private function rawBody(Message $message): string
    {
        try {
            return (string) $message->getRawBody();
        } catch (Throwable) {
            return '';
        }
    }

    private function attachmentBodies(Message $message): string
    {
        $parts = [];

        try {
            foreach ($message->getAttachments() as $attachment) {
                $contentType = strtolower((string) $attachment->getContentType());
                if (
                    str_contains($contentType, 'delivery-status')
                    || str_contains($contentType, 'rfc822')
                    || str_contains($contentType, 'text/')
                ) {
                    $parts[] = (string) $attachment->getContent();
                }
            }
        } catch (Throwable) {
            return '';
        }

        return implode("\n", $parts);
    }
}
