<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\History;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\History\BlameOrigin;
use DH\Auditor\History\Change;
use DH\Auditor\History\ChangeExtractor;
use DH\Auditor\History\ChangeKind;
use DH\Auditor\History\HistoryGrouper;
use DH\Auditor\History\HistoryItem;
use DH\Auditor\Model\Entry;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class HistoryGrouperTest extends TestCase
{
    public function testImpersonatorOfTheMostRecentEntry(): void
    {
        $impersonator = ['id' => '1', 'username' => 'admin', 'fqdn' => 'App\Entity\Admin'];
        $entries = [
            EntryFactory::entry('update', 'App\Post', '1', ['changes' => ['title' => ['old' => 'a', 'new' => 'b']]], 'T1', ['id' => 42, 'username' => 'alice', 'user_firewall' => 'main', 'impersonator' => $impersonator]),
            EntryFactory::entry('update', 'App\Post', '2', ['changes' => ['title' => ['old' => 'a', 'new' => 'b']]], 'T2', ['id' => 42, 'username' => 'alice', 'user_firewall' => 'main'], '2026-11-23 09:00:00'),
        ];

        $items = HistoryGrouper::byTransaction($entries);

        $this->assertSame($impersonator, $items[0]->impersonator);
        $this->assertNull($items[1]->impersonator, 'No impersonator when nobody impersonated the author.');
    }

    public function testGroupsByTransaction(): void
    {
        $alice = ['id' => 1, 'username' => 'alice', 'user_firewall' => 'main'];
        $entries = [
            EntryFactory::entry('update', 'App\Post', '1', ['changes' => ['title' => ['old' => 'a', 'new' => 'b']]], 'T2', ['via' => ['type' => 'console', 'label' => 'app:sync']], '2026-11-23 11:00:00'),
            EntryFactory::entry('update', 'App\Comment', '5', ['changes' => ['body' => ['old' => 'x', 'new' => 'y']]], 'T1', $alice, '2026-11-23 10:00:01'),
            EntryFactory::entry('insert', 'App\Post', '1', ['changes' => ['title' => ['new' => 'a']]], 'T1', $alice, '2026-11-23 10:00:00'),
        ];

        $items = HistoryGrouper::byTransaction($entries);

        $this->assertCount(2, $items);
        $this->assertSame('T2', $items[0]->key);
        $this->assertSame(BlameOrigin::Console, $items[0]->origin);
        $this->assertSame(['type' => 'console', 'label' => 'app:sync'], $items[0]->via);
        $this->assertNull($items[0]->username);

        $this->assertSame('T1', $items[1]->key);
        $this->assertSame('alice', $items[1]->username);
        $this->assertSame(1, $items[1]->userId);
        $this->assertSame(BlameOrigin::Http, $items[1]->origin);
        $this->assertSame('2026-11-23 10:00:01', $items[1]->createdAt->format('Y-m-d H:i:s'));
        $this->assertSame(['T1'], $items[1]->getTransactionIds());
        $this->assertSame(['App\Comment', 'App\Post'], $items[1]->getEntities());

        $changes = $items[1]->getChanges();
        $this->assertSame([ChangeKind::Created, ChangeKind::Changed], array_map(static fn (Change $c): ChangeKind => $c->kind, $changes), 'Changes are in chronological order.');
        $this->assertSame(ChangeKind::Created, $items[1]->getMainChange()?->kind);
        $this->assertSame(ChangeKind::Changed, $items[0]->getMainChange()?->kind);
    }

    public function testCustomKey(): void
    {
        $request = static fn (string $id): array => ['request_id' => $id];
        $entries = [
            $this->withExtraData(EntryFactory::entry('update', 'App\Post', '1', ['changes' => ['a' => ['old' => 1, 'new' => 2]]], 'T3'), $request('R1')),
            $this->withExtraData(EntryFactory::entry('update', 'App\Post', '1', ['changes' => ['b' => ['old' => 1, 'new' => 2]]], 'T2'), $request('R1')),
            EntryFactory::entry('update', 'App\Post', '1', ['changes' => ['c' => ['old' => 1, 'new' => 2]]], 'T1'),
        ];

        $grouper = new HistoryGrouper(static fn (Entry $entry): ?string => $entry->extraData['request_id'] ?? null);
        $items = $grouper->group($entries);

        $this->assertCount(2, $items);
        $this->assertSame('R1', $items[0]->key);
        $this->assertSame(['T3', 'T2'], $items[0]->getTransactionIds());
        $this->assertSame('T1', $items[1]->key, 'A null key falls back to the transaction id.');
        $this->assertSame('R1', $grouper->keyOf($entries[0]));
    }

    public function testGroupsByTransactionUnlessItHasAKeyOfItsOwn(): void
    {
        $this->assertTrue(new HistoryGrouper()->groupsByTransaction());
        $this->assertTrue(new HistoryGrouper(extractor: new ChangeExtractor(['App\Post' => ['title']]))->groupsByTransaction(), 'An extractor of its own keeps the grouping by transaction.');
        $this->assertFalse(new HistoryGrouper(static fn (Entry $entry): ?string => null)->groupsByTransaction());
    }

    public function testEntryWithoutTransaction(): void
    {
        $entry = Entry::fromArray(['id' => 9, 'type' => 'update', 'object_id' => '1', 'entity' => 'App\Post']);

        $this->assertSame('App\Post#9', HistoryGrouper::transactionKey($entry));
    }

    public function testAnItemNeedsEntries(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HistoryItem('key', []);
    }

    public function testNumericKeysStayStrings(): void
    {
        $grouper = new HistoryGrouper(static fn (Entry $entry): string => '123');

        $items = $grouper->group([EntryFactory::entry('update', 'App\Post', '1', ['changes' => ['title' => ['old' => 'a', 'new' => 'b']]])]);

        $this->assertSame('123', $items[0]->key);
    }

    /**
     * @param array<string, mixed> $extraData
     */
    private function withExtraData(Entry $entry, array $extraData): Entry
    {
        $row = $entry->toArray();

        return Entry::fromArray([
            'id' => $entry->id,
            'schema_version' => 2,
            'type' => $entry->type,
            'object_id' => $entry->objectId,
            'transaction_id' => $entry->transactionId,
            'diffs' => json_encode(['source' => $entry->getDiffSource(), 'changes' => $row['diffs']], JSON_THROW_ON_ERROR),
            'extra_data' => json_encode($extraData, JSON_THROW_ON_ERROR),
            'created_at' => $entry->createdAt,
            'entity' => $entry->entity,
        ]);
    }
}
