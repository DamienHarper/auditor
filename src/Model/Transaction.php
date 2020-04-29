<?php

namespace DH\Auditor\Model;

class Transaction
{
    public const INSERT = 'insert';
    public const UPDATE = 'update';
    public const REMOVE = 'remove';
    public const ASSOCIATE = 'associate';
    public const DISSOCIATE = 'dissociate';

    /**
     * @var null|string
     */
    private $transaction_hash;

    /**
     * @var array
     */
    private $inserted = [];     // [$source, $changeset]

    /**
     * @var array
     */
    private $updated = [];      // [$source, $changeset]

    /**
     * @var array
     */
    private $removed = [];      // [$source, $id]

    /**
     * @var array
     */
    private $associated = [];   // [$source, $target, $mapping]

    /**
     * @var array
     */
    private $dissociated = [];  // [$source, $target, $id, $mapping]

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
