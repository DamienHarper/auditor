<?php

declare(strict_types=1);

namespace DH\Auditor\Model;

/**
 * @see \DH\Auditor\Tests\Model\TransactionTest
 */
class Transaction implements TransactionInterface
{
    public const INSERT = 'insert';
    public const UPDATE = 'update';
    public const REMOVE = 'remove';
    public const ASSOCIATE = 'associate';
    public const DISSOCIATE = 'dissociate';

    private ?string $transaction_hash = null;

    private array $inserted = [];     // [$source, $changeset]
    private array $updated = [];      // [$source, $changeset]
    private array $removed = [];      // [$source, $id]
    private array $associated = [];   // [$source, $target, $mapping]
    private array $dissociated = [];  // [$source, $target, $id, $mapping]

    /**
     * Returns transaction hash.
     */
    public function getTransactionHash(): string
    {
        if (null === $this->transaction_hash) {
            $this->transaction_hash = sha1(uniqid('tid', true));
        }

        return $this->transaction_hash;
    }

    public function getInserted(): array
    {
        return $this->inserted;
    }

    public function getUpdated(): array
    {
        return $this->updated;
    }

    public function getRemoved(): array
    {
        return $this->removed;
    }

    public function getAssociated(): array
    {
        return $this->associated;
    }

    public function getDissociated(): array
    {
        return $this->dissociated;
    }

    public function reset(): void
    {
        $this->transaction_hash = null;
        $this->inserted = [];
        $this->updated = [];
        $this->removed = [];
        $this->associated = [];
        $this->dissociated = [];
    }

    public function trackAuditEvent(string $type, array $data): void
    {
        switch ($type) {
            case self::INSERT:
                $this->inserted[] = $data;

                break;

            case self::UPDATE:
                $this->updated[] = $data;

                break;

            case self::REMOVE:
                $this->removed[] = $data;

                break;

            case self::ASSOCIATE:
                $this->associated[] = $data;

                break;

            case self::DISSOCIATE:
                $this->dissociated[] = $data;

                break;
        }
    }
}
