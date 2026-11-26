<?php

declare(strict_types=1);

namespace DH\Auditor\History;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Model\Entry;
use DH\Auditor\Tests\History\HistoryGrouperTest;

/**
 * One action of a history: the audit entries sharing a grouping key (by default, one database transaction).
 *
 * The author, the origin and the blame context are read from the most recent entry. The raw entries stay available
 * for technical details.
 *
 * @see HistoryGrouperTest
 */
final class HistoryItem
{
    /**
     * Date of the most recent entry of the item.
     */
    public \DateTimeImmutable $createdAt {
        get => $this->entries[0]->createdAt ?? new \DateTimeImmutable('@0');
    }

    public int|string|null $userId {
        get => $this->entries[0]->userId;
    }

    /**
     * Name of the author when the blame holds one. A null username with a non-null user id is a user whose name was
     * not recorded (or a deleted user); both null means a change made without a user (a command, a worker…).
     */
    public ?string $username {
        get => $this->entries[0]->username;
    }

    public ?string $userFqdn {
        get => $this->entries[0]->userFqdn;
    }

    /**
     * User who impersonated the author (switch user), from the blame.
     *
     * @var null|array{id?: string, username?: string, fqdn?: string}
     */
    public ?array $impersonator {
        get => $this->entries[0]->impersonator;
    }

    public BlameOrigin $origin {
        get => BlameOrigin::fromEntry($this->entries[0]);
    }

    /**
     * @var null|array{type: string, label?: string, data?: array<string, mixed>}
     */
    public ?array $via {
        get => $this->entries[0]->via;
    }

    /**
     * @var null|list<Change>
     */
    private ?array $changes = null;

    /**
     * @param non-empty-list<Entry> $entries entries of the item, most recent first
     *
     * @throws InvalidArgumentException when $entries is empty
     */
    public function __construct(
        public readonly string $key,
        public readonly array $entries,
        private readonly ChangeExtractor $extractor = new ChangeExtractor(),
    ) {
        if ([] === $entries) {
            throw new InvalidArgumentException('A history item needs at least one entry.');
        }
    }

    /**
     * Changes of the item in chronological order, without the mirror entries of bidirectional associations.
     *
     * @return list<Change>
     */
    public function getChanges(): array
    {
        return $this->changes ??= $this->extractor->extractAll(array_reverse($this->entries));
    }

    /**
     * The change that best sums the item up: the first creation, snapshot or deletion, otherwise the first change.
     */
    public function getMainChange(): ?Change
    {
        $changes = $this->getChanges();

        return array_find($changes, static fn (Change $change): bool => $change->kind->isEntityLevel()) ?? $changes[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getTransactionIds(): array
    {
        $ids = [];
        foreach ($this->entries as $entry) {
            if (null !== $entry->transactionId) {
                $ids[$entry->transactionId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * FQCNs of the entities changed in the item.
     *
     * @return list<string>
     */
    public function getEntities(): array
    {
        $entities = [];
        foreach ($this->entries as $entry) {
            $entities[ChangeExtractor::entityOf($entry)] = true;
        }

        return array_keys($entities);
    }
}
