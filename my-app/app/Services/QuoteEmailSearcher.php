<?php

namespace App\Services;

use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Query\WhereQuery;

class QuoteEmailSearcher
{
    /**
     * Where Avinode trip threads land. Revisit if quote emails start
     * arriving somewhere else (a dedicated folder/rule, etc).
     */
    private const FOLDER = 'INBOX';

    /**
     * Cap on how many matching messages get their full body fetched. The
     * IMAP SEARCH itself always scans the whole mailbox regardless of this
     * limit (see config/imap.php's comment on the account timeout), but a
     * too-generic trip ID can still match thousands of unrelated messages —
     * without a cap, fetching all of their bodies is what would actually
     * blow the request's time and memory budget. Newest matches are kept.
     */
    private const MAX_RESULTS = 50;

    public function __construct(private readonly ClientManager $manager)
    {
    }

    /**
     * Search the configured mailbox for messages referencing the given
     * Avinode trip ID.
     *
     * This is a raw retrieval step only — no parsing into structured quote
     * data yet. IMAP's SUBJECT/BODY search keys are already substring,
     * case-insensitive matches (RFC 3501), so "AVI-1234" also matches a
     * subject like "Re: Quote request AVI-1234-2".
     *
     * Trip IDs consistently show up in the subject line, and a SUBJECT-only
     * search only has to scan cached envelope data — it's fast even on this
     * unindexed 26k-message mailbox. A BODY search, by contrast, has to
     * scan every message's full content and reliably takes ~60s+ here (see
     * config/imap.php's comment on the account timeout). So we try
     * SUBJECT-only first and only pay for the combined SUBJECT+BODY search
     * if that comes back empty — covering the rare email that mentions the
     * trip ID only in its body.
     *
     * @return array{
     *     emails: list<array{from: string, subject: string, date: ?string, body: string}>,
     *     total_matches: int,
     *     truncated: bool,
     *     search_scope: 'subject'|'subject_and_body',
     * }
     *
     * @throws ConnectionFailedException if the mailbox can't be reached or
     *                                    the configured credentials are rejected.
     */
    public function search(string $tripId): array
    {
        $client = $this->manager->account('default');
        $client->connect();

        try {
            $folder = $client->getFolder(self::FOLDER);

            $result = $this->runQuery($folder->query()->subject($tripId), $tripId);
            $result['search_scope'] = 'subject';

            if ($result['total_matches'] === 0) {
                $result = $this->runQuery(
                    $folder->query()->orWhere()->subject($tripId)->body($tripId),
                    $tripId
                );
                $result['search_scope'] = 'subject_and_body';
            }

            return $result;
        } finally {
            $client->disconnect();
        }
    }

    /**
     * Run an already-built where query and shape it into the response's
     * email list. Shared by both the SUBJECT-only pass and the
     * SUBJECT+BODY fallback so they're fetched/mapped identically.
     *
     * @return array{emails: list<array{from: string, subject: string, date: ?string, body: string}>, total_matches: int, truncated: bool}
     */
    private function runQuery(WhereQuery $query, string $tripId): array
    {
        $messages = $query
            // Never mark these as read just because we looked — this is
            // read-only retrieval, not mail processing.
            ->leaveUnread()
            ->setFetchOrder('desc')
            ->limit(self::MAX_RESULTS)
            ->get();

        $totalMatches = $messages->total() ?? $messages->count();

        $emails = $messages
            ->sortByDesc(fn ($message) => $message->date->first())
            ->values()
            ->map(fn ($message) => [
                'from' => (string) $message->from,
                'subject' => (string) $message->subject,
                'date' => optional($message->date->first())->toIso8601String(),
                'body' => $message->getTextBody(),
            ])
            ->all();

        return [
            'emails' => $emails,
            'total_matches' => $totalMatches,
            'truncated' => $totalMatches > count($emails),
        ];
    }
}
