<?php

declare(strict_types=1);

namespace DH\Auditor\History;

use DH\Auditor\Model\Entry;
use DH\Auditor\Tests\History\HistoryGrouperTest;

/**
 * Groups audit entries into actions (HistoryItem).
 *
 * The grouping key defaults to the transaction id. Any callable can replace it, such as an id that an application
 * writes in extra_data to group the transactions of one request:
 *
 *     new HistoryGrouper(static fn (Entry $entry): ?string => $entry->extraData['request_id'] ?? null);
 *
 * A null key falls back to the transaction id. Items keep the order in which their first entry is read: pass the
 * entries most recent first, as readers return them.
 *
 * @see HistoryGrouperTest
 */
final readonly class HistoryGrouper
{
    /**
     * @var null|\Closure(Entry): (null|int|string)
     */
    private ?\Closure $key;

    /**
     * @param null|callable(Entry): (null|int|string) $key
     */
    public function __construct(?callable $key = null, private ChangeExtractor $extractor = new ChangeExtractor())
    {
        $this->key = null === $key ? null : $key(...);
    }

    /**
     * Groups by transaction with the default change extractor.
     *
     * @param iterable<Entry> $entries
     *
     * @return list<HistoryItem>
     */
    public static function byTransaction(iterable $entries): array
    {
        return new self()->group($entries);
    }

    /**
     * Key of the default grouping: the transaction id, or the entry itself when it has none.
     */
    public static function transactionKey(Entry $entry): string
    {
        return $entry->transactionId ?? ChangeExtractor::entityOf($entry).'#'.$entry->id;
    }

    /**
     * Returns the grouping key of an entry.
     */
    public function keyOf(Entry $entry): string
    {
        $key = null === $this->key ? null : ($this->key)($entry);

        return null === $key || '' === $key ? self::transactionKey($entry) : (string) $key;
    }

    /**
     * @param iterable<Entry> $entries entries, most recent first
     *
     * @return list<HistoryItem>
     */
    public function group(iterable $entries): array
    {
        $groups = [];
        $keys = [];
        foreach ($entries as $entry) {
            $key = $this->keyOf($entry);
            $groups[$key][] = $entry;
            $keys[$key] = $key; // a numeric string key becomes an int array key: keep the string
        }

        $items = [];
        foreach ($groups as $index => $groupEntries) {
            $items[] = new HistoryItem($keys[$index], $groupEntries, $this->extractor);
        }

        return $items;
    }
}
