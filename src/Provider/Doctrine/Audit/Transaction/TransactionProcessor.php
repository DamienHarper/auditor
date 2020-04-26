<?php

namespace DH\Auditor\Provider\Doctrine\Audit\Transaction;

use DateTime;
use DateTimeZone;
use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Provider\Doctrine\Model\Transaction;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Provider\ProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

class TransactionProcessor
{
    use AuditTrait;

    /**
     * @var ProviderInterface
     */
    private $provider;

    public function __construct(ProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    public function process(Transaction $transaction): void
    {
        $this->processInsertions($transaction, $transaction->getEntityManager());
        $this->processUpdates($transaction, $transaction->getEntityManager());
        $this->processAssociations($transaction, $transaction->getEntityManager());
        $this->processDissociations($transaction, $transaction->getEntityManager());
        $this->processDeletions($transaction, $transaction->getEntityManager());
    }

    private function notify(array $payload): void
    {
        $dispatcher = $this->provider->getAuditor()->getEventDispatcher();

        if ($this->provider->getAuditor()->isPre43Dispatcher()) {
            // Symfony 3.x
            $dispatcher->dispatch(LifecycleEvent::class, new LifecycleEvent($payload));
        } else {
            // Symfony 4.x
            $dispatcher->dispatch(new LifecycleEvent($payload));
        }
    }

    /**
     * Adds an insert entry to the audit table.
     *
     * @param mixed $entity
     */
    private function insert(EntityManagerInterface $entityManager, $entity, array $ch, string $transactionHash): void
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
     *
     * @param mixed $entity
     */
    private function update(EntityManagerInterface $entityManager, $entity, array $ch, string $transactionHash): void
    {
        $diff = $this->diff($entityManager, $entity, $ch);
        if (0 === \count($diff)) {
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
     *
     * @param mixed $entity
     * @param mixed $id
     */
    private function remove(EntityManagerInterface $entityManager, $entity, $id, string $transactionHash): void
    {
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($entity));
        $this->audit([
            'action' => 'remove',
            'blame' => $this->blame(),
            'diff' => $this->summarize($entityManager, $entity, $id),
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
     *
     * @param mixed $source
     * @param mixed $target
     */
    private function associate(EntityManagerInterface $entityManager, $source, $target, array $mapping, string $transactionHash): void
    {
        $this->associateOrDissociate('associate', $entityManager, $source, $target, $mapping, $transactionHash);
    }

    /**
     * Adds a dissociation entry to the audit table.
     *
     * @param mixed $source
     * @param mixed $target
     */
    private function dissociate(EntityManagerInterface $entityManager, $source, $target, array $mapping, string $transactionHash): void
    {
        $this->associateOrDissociate('dissociate', $entityManager, $source, $target, $mapping, $transactionHash);
    }

    private function processInsertions(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        $uow = $entityManager->getUnitOfWork();
        foreach ($transaction->getInserted() as [$entity, $ch]) {
            // the changeset might be updated from UOW extra updates
            $ch = array_merge($ch, $uow->getEntityChangeSet($entity));
            $this->insert($entityManager, $entity, $ch, $transaction->getTransactionHash());
        }
    }

    private function processUpdates(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        $uow = $entityManager->getUnitOfWork();
        foreach ($transaction->getUpdated() as [$entity, $ch]) {
            // the changeset might be updated from UOW extra updates
            $ch = array_merge($ch, $uow->getEntityChangeSet($entity));
            $this->update($entityManager, $entity, $ch, $transaction->getTransactionHash());
        }
    }

    private function processAssociations(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        foreach ($transaction->getAssociated() as [$source, $target, $mapping]) {
            $this->associate($entityManager, $source, $target, $mapping, $transaction->getTransactionHash());
        }
    }

    private function processDissociations(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        foreach ($transaction->getDissociated() as [$source, $target, $id, $mapping]) {
            $this->dissociate($entityManager, $source, $target, $mapping, $transaction->getTransactionHash());
        }
    }

    private function processDeletions(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        foreach ($transaction->getRemoved() as [$entity, $id]) {
            $this->remove($entityManager, $entity, $id, $transaction->getTransactionHash());
        }
    }

    /**
     * Adds an association entry to the audit table.
     *
     * @param mixed $source
     * @param mixed $target
     */
    private function associateOrDissociate(string $type, EntityManagerInterface $entityManager, $source, $target, array $mapping, string $transactionHash): void
    {
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($source));
        $data = [
            'action' => $type,
            'blame' => $this->blame(),
            'diff' => [
                'source' => $this->summarize($entityManager, $source),
                'target' => $this->summarize($entityManager, $target),
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
        $schema = $data['schema'] ? $data['schema'].'.' : '';
        $auditTable = $schema.$this->provider->getConfiguration()->getTablePrefix().$data['table'].$this->provider->getConfiguration()->getTableSuffix();
        $dt = new DateTime('now', new DateTimeZone($this->provider->getAuditor()->getConfiguration()->getTimezone()));

        $payload = [
            'entity' => $data['entity'],
            'table' => $auditTable,
            'type' => $data['action'],
            'object_id' => (string) $data['id'],
            'discriminator' => $data['discriminator'],
            'transaction_hash' => (string) $data['transaction_hash'],
            'diffs' => json_encode($data['diff']),
            'blame_id' => $data['blame']['user_id'],
            'blame_user' => $data['blame']['username'],
            'blame_user_fqdn' => $data['blame']['user_fqdn'],
            'blame_user_firewall' => $data['blame']['user_firewall'],
            'ip' => $data['blame']['client_ip'],
            'created_at' => $dt->format('Y-m-d H:i:s'),
        ];

        // send an `AuditEvent` event
        $this->notify($payload);
    }

    private function getDiscriminator($entity, int $inheritanceType): ?string
    {
        return ClassMetadata::INHERITANCE_TYPE_SINGLE_TABLE === $inheritanceType ? DoctrineHelper::getRealClassName($entity) : null;
    }
}
