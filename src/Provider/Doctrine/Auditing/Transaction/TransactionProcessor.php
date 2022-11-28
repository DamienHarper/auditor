<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Transaction;

use DateTimeImmutable;
use DateTimeZone;
use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Model\TransactionInterface;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Model\Transaction;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Transaction\TransactionProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * @see \DH\Auditor\Tests\Provider\Doctrine\Auditing\Transaction\TransactionProcessorTest
 */
class TransactionProcessor implements TransactionProcessorInterface
{
    use AuditTrait;

    private DoctrineProvider $provider;

    public function __construct(DoctrineProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * @param Transaction $transaction
     */
    public function process(TransactionInterface $transaction): void
    {
        $em = $transaction->getEntityManager();
        $this->processInsertions($transaction, $em);
        $this->processUpdates($transaction, $em);
        $this->processAssociations($transaction, $em);
        $this->processDissociations($transaction, $em);
        $this->processDeletions($transaction, $em);
    }

    private function notify(array $payload): void
    {
        $dispatcher = $this->provider->getAuditor()->getEventDispatcher();
        $dispatcher->dispatch(new LifecycleEvent($payload));
    }

    /**
     * Adds an insert entry to the audit table.
     */
    private function insert(EntityManagerInterface $entityManager, object $entity, array $ch, string $transactionHash): void
    {
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($entity));
        $this->audit([
            'action' => 'insert',
            'blame' => $this->blame(),
            'diff' => $this->diff($entityManager, $entity, $ch),
            'table' => $meta->getTableName(),
            'schema' => $meta->getSchemaName(),
            'id' => $this->id($entityManager, $entity),
            'transaction_hash' => $transactionHash,
            'discriminator' => $this->getDiscriminator($entity, $meta->inheritanceType),
            'entity' => $meta->getName(),
        ]);
    }

    /**
     * Adds an update entry to the audit table.
     */
    private function update(EntityManagerInterface $entityManager, object $entity, array $ch, string $transactionHash): void
    {
        $diff = $this->diff($entityManager, $entity, $ch);
        unset($diff['@source']);

        if ([] === $diff) {
            return; // if there is no entity diff, do not log it
        }

        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($entity));
        $this->audit([
            'action' => 'update',
            'blame' => $this->blame(),
            'diff' => $diff,
            'table' => $meta->getTableName(),
            'schema' => $meta->getSchemaName(),
            'id' => $this->id($entityManager, $entity),
            'transaction_hash' => $transactionHash,
            'discriminator' => $this->getDiscriminator($entity, $meta->inheritanceType),
            'entity' => $meta->getName(),
        ]);
    }

    /**
     * Adds a remove entry to the audit table.
     */
    private function remove(EntityManagerInterface $entityManager, object $entity, mixed $id, string $transactionHash): void
    {
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($entity));
        $this->audit([
            'action' => 'remove',
            'blame' => $this->blame(),
            'diff' => $this->summarize($entityManager, $entity, ['id' => $id]),
            'table' => $meta->getTableName(),
            'schema' => $meta->getSchemaName(),
            'id' => $id,
            'transaction_hash' => $transactionHash,
            'discriminator' => $this->getDiscriminator($entity, $meta->inheritanceType),
            'entity' => $meta->getName(),
        ]);
    }

    /**
     * Adds an association entry to the audit table.
     */
    private function associate(EntityManagerInterface $entityManager, object $source, object $target, array $mapping, string $transactionHash): void
    {
        $this->associateOrDissociate('associate', $entityManager, $source, $target, $mapping, $transactionHash);
    }

    /**
     * Adds a dissociation entry to the audit table.
     */
    private function dissociate(EntityManagerInterface $entityManager, object $source, object $target, array $mapping, string $transactionHash): void
    {
        $this->associateOrDissociate('dissociate', $entityManager, $source, $target, $mapping, $transactionHash);
    }

    private function processInsertions(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        $uow = $entityManager->getUnitOfWork();
        foreach ($transaction->getInserted() as $dto) {
            // the changeset might be updated from UOW extra updates
            $ch = array_merge($dto->getChangeset(), $uow->getEntityChangeSet($dto->getSource()));
            $this->insert($entityManager, $dto->getSource(), $ch, $transaction->getTransactionHash());
        }
    }

    private function processUpdates(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        $uow = $entityManager->getUnitOfWork();
        foreach ($transaction->getUpdated() as $dto) {
            // the changeset might be updated from UOW extra updates
            $ch = array_merge($dto->getChangeset(), $uow->getEntityChangeSet($dto->getSource()));
            $this->update($entityManager, $dto->getSource(), $ch, $transaction->getTransactionHash());
        }
    }

    private function processAssociations(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        foreach ($transaction->getAssociated() as $dto) {
            $this->associate($entityManager, $dto->getSource(), $dto->getTarget(), $dto->getMapping(), $transaction->getTransactionHash());
        }
    }

    private function processDissociations(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        foreach ($transaction->getDissociated() as $dto) {
            $this->dissociate($entityManager, $dto->getSource(), $dto->getTarget(), $dto->getMapping(), $transaction->getTransactionHash());
        }
    }

    private function processDeletions(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        foreach ($transaction->getRemoved() as $dto) {
            $this->remove($entityManager, $dto->getSource(), $dto->getId(), $transaction->getTransactionHash());
        }
    }

    /**
     * Adds an association entry to the audit table.
     */
    private function associateOrDissociate(string $type, EntityManagerInterface $entityManager, object $source, object $target, array $mapping, string $transactionHash): void
    {
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($source));
        $data = [
            'action' => $type,
            'blame' => $this->blame(),
            'diff' => [
                'source' => $this->summarize($entityManager, $source, ['field' => $mapping['fieldName']]),
                'target' => $this->summarize($entityManager, $target, ['field' => $mapping['isOwningSide'] ? $mapping['inversedBy'] : $mapping['mappedBy']]),
                'is_owning_side' => $mapping['isOwningSide'],
            ],
            'table' => $meta->getTableName(),
            'schema' => $meta->getSchemaName(),
            'id' => $this->id($entityManager, $source),
            'transaction_hash' => $transactionHash,
            'discriminator' => $this->getDiscriminator($source, $meta->inheritanceType),
            'entity' => $meta->getName(),
        ];

        if (isset($mapping['joinTable']['name'])) {
            $data['diff']['table'] = $mapping['joinTable']['name'];
        }

        $this->audit($data);
    }

    /**
     * Adds an entry to the audit table.
     */
    private function audit(array $data): void
    {
        /** @var Configuration $configuration */
        $configuration = $this->provider->getConfiguration();
        $schema = $data['schema'] ? $data['schema'].'.' : '';
        $auditTable = $schema.$configuration->getTablePrefix().$data['table'].$configuration->getTableSuffix();
        $dt = new DateTimeImmutable('now', new DateTimeZone($this->provider->getAuditor()->getConfiguration()->getTimezone()));

        $payload = [
            'entity' => $data['entity'],
            'table' => $auditTable,
            'type' => $data['action'],
            'object_id' => (string) $data['id'],
            'discriminator' => $data['discriminator'],
            'transaction_hash' => (string) $data['transaction_hash'],
            'diffs' => json_encode($data['diff'], JSON_THROW_ON_ERROR),
            'blame_id' => $data['blame']['user_id'],
            'blame_user' => $data['blame']['username'],
            'blame_user_fqdn' => $data['blame']['user_fqdn'],
            'blame_user_firewall' => $data['blame']['user_firewall'],
            'ip' => $data['blame']['client_ip'],
            'created_at' => $dt->format('Y-m-d H:i:s.u'),
        ];

        // send an `AuditEvent` event
        $this->notify($payload);
    }

    private function getDiscriminator(object $entity, int $inheritanceType): ?string
    {
        return ClassMetadata::INHERITANCE_TYPE_SINGLE_TABLE === $inheritanceType ? DoctrineHelper::getRealClassName($entity) : null;
    }
}
