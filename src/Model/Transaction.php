<?php

declare(strict_types=1);

namespace DH\Auditor\Model;

use DH\Auditor\Event\Dto\AssociateEventDto;
use DH\Auditor\Event\Dto\DissociateEventDto;
use DH\Auditor\Event\Dto\InsertEventDto;
use DH\Auditor\Event\Dto\RemoveEventDto;
use DH\Auditor\Event\Dto\UpdateEventDto;

/**
 * @see \DH\Auditor\Tests\Model\TransactionTest
 */
class Transaction implements TransactionInterface
{
    /**
     * @var string
     */
    public const INSERT = 'insert';

    /**
     * @var string
     */
    public const UPDATE = 'update';

    /**
     * @var string
     */
    public const REMOVE = 'remove';

    /**
     * @var string
     */
    public const ASSOCIATE = 'associate';

    /**
     * @var string
     */
    public const DISSOCIATE = 'dissociate';

    private ?string $transaction_hash = null;

    /**
     * @var InsertEventDto[]
     */
    private array $inserted = [];

    /**
     * @var UpdateEventDto[]
     */
    private array $updated = [];

    /**
     * @var RemoveEventDto[]
     */
    private array $removed = [];

    /**
     * @var AssociateEventDto[]
     */
    private array $associated = [];

    /**
     * @var DissociateEventDto[]
     */
    private array $dissociated = [];

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

    /**
     * @return array<InsertEventDto>
     */
    public function getInserted(): array
    {
        return $this->inserted;
    }

    /**
     * @return array<UpdateEventDto>
     */
    public function getUpdated(): array
    {
        return $this->updated;
    }

    /**
     * @return array<RemoveEventDto>
     */
    public function getRemoved(): array
    {
        return $this->removed;
    }

    /**
     * @return array<AssociateEventDto>
     */
    public function getAssociated(): array
    {
        return $this->associated;
    }

    /**
     * @return array<DissociateEventDto>
     */
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

    /**
     * TODO: remove for next major release.
     *
     * @internal
     *
     * @deprecated use one of the insert/update/remove/associate/dissociate methods instead
     */
    public function trackAuditEvent(string $type, array $data): void
    {
        @trigger_error('This method is deprecated, use one of the Transaction::insert(), Transaction::update(), Transaction::remove(), Transaction::associate(), Transaction::dissociate() methods instead.', E_USER_DEPRECATED);

        switch ($type) {
            case self::INSERT:
                \assert(2 === \count($data));
                $this->insert(...$data);

                break;

            case self::UPDATE:
                \assert(2 === \count($data));
                $this->update(...$data);

                break;

            case self::REMOVE:
                \assert(2 === \count($data));
                $this->remove(...$data);

                break;

            case self::ASSOCIATE:
                \assert(3 === \count($data));
                $this->associate(...$data);

                break;

            case self::DISSOCIATE:
                \assert(4 === \count($data));
                $this->dissociate(...$data);

                break;
        }
    }

    public function insert(object $source, array $changeset): void
    {
        $this->inserted[] = new InsertEventDto($source, $changeset);
    }

    public function update(object $source, array $changeset): void
    {
        $this->updated[] = new UpdateEventDto($source, $changeset);
    }

    public function remove(object $source, mixed $id): void
    {
        $this->removed[] = new RemoveEventDto($source, $id);
    }

    public function associate(object $source, object $target, array $mapping): void
    {
        $this->associated[] = new AssociateEventDto($source, $target, $mapping);
    }

    public function dissociate(object $source, object $target, array $mapping): void
    {
        $this->dissociated[] = new DissociateEventDto($source, $target, $mapping);
    }
}
