<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Transaction;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Model\TransactionInterface;
use DH\Auditor\Model\TransactionType;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Model\Transaction;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use DH\Auditor\Tests\Provider\Doctrine\Auditing\Transaction\TransactionProcessorTest;
use DH\Auditor\Transaction\TransactionProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * @see TransactionProcessorTest
 */
final class TransactionProcessor implements TransactionProcessorInterface
{
    use AuditTrait;

    private ?\DateTimeZone $dateTimeZone = null;

    public function __construct(private DoctrineProvider $provider) {}

    /**
     * @param Transaction $transaction
     */
    public function process(TransactionInterface $transaction): void
    {
        $em = $transaction->getEntityManager();
        $blame = $this->blame();
        $this->processInsertions($transaction, $em, $blame);
        $this->processUpdates($transaction, $em, $blame);
        $this->processAssociations($transaction, $em, $blame);
        $this->processDissociations($transaction, $em, $blame);
        $this->processDeletions($transaction, $em, $blame);
    }

    private function notify(array $payload, ?object $entity = null): void
    {
        $dispatcher = $this->provider->getAuditor()->getEventDispatcher();
        $dispatcher->dispatch(new LifecycleEvent($payload, $entity));
    }

    /**
     * Adds an insert entry to the audit table.
     */
    private function insert(EntityManagerInterface $entityManager, object $entity, array $ch, string $transactionHash, array $blame): void
    {
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($entity));
        $this->audit([
            'action' => TransactionType::Insert,
            'blame' => $blame,
            'diff' => $this->diff($entityManager, $entity, $ch, $meta),
            'table' => $meta->getTableName(),
            'schema' => $meta->getSchemaName(),
            'id' => $this->id($entityManager, $entity, $meta),
            'transaction_hash' => $transactionHash,
            'discriminator' => $this->getDiscriminator($entity, $meta->inheritanceType),
            'entity' => $meta->getName(),
            'entity_object' => $entity,
        ]);
    }

    /**
     * Adds an update entry to the audit table.
     */
    private function update(EntityManagerInterface $entityManager, object $entity, array $ch, string $transactionHash, array $blame): void
    {
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($entity));
        $diff = $this->diff($entityManager, $entity, $ch, $meta);
        unset($diff['@source']);

        if ([] === $diff) {
            return; // if there is no entity diff, do not log it
        }

        $this->audit([
            'action' => TransactionType::Update,
            'blame' => $blame,
            'diff' => $diff,
            'table' => $meta->getTableName(),
            'schema' => $meta->getSchemaName(),
            'id' => $this->id($entityManager, $entity, $meta),
            'transaction_hash' => $transactionHash,
            'discriminator' => $this->getDiscriminator($entity, $meta->inheritanceType),
            'entity' => $meta->getName(),
            'entity_object' => $entity,
        ]);
    }

    /**
     * Adds a remove entry to the audit table.
     */
    private function remove(EntityManagerInterface $entityManager, object $entity, mixed $id, string $transactionHash, array $blame): void
    {
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($entity));
        $this->audit([
            'action' => TransactionType::Remove,
            'blame' => $blame,
            'diff' => $this->summarize($entityManager, $entity, ['id' => $id], $meta),
            'table' => $meta->getTableName(),
            'schema' => $meta->getSchemaName(),
            'id' => $id,
            'transaction_hash' => $transactionHash,
            'discriminator' => $this->getDiscriminator($entity, $meta->inheritanceType),
            'entity' => $meta->getName(),
            'entity_object' => $entity,
        ]);
    }

    /**
     * Adds an association entry to the audit table.
     */
    private function associate(EntityManagerInterface $entityManager, object $source, object $target, array $mapping, string $transactionHash, array $blame): void
    {
        $this->associateOrDissociate(TransactionType::Associate, $entityManager, $source, $target, $mapping, $transactionHash, $blame);
    }

    /**
     * Adds a dissociation entry to the audit table.
     */
    private function dissociate(EntityManagerInterface $entityManager, object $source, object $target, array $mapping, string $transactionHash, array $blame): void
    {
        $this->associateOrDissociate(TransactionType::Dissociate, $entityManager, $source, $target, $mapping, $transactionHash, $blame);
    }

    private function processInsertions(Transaction $transaction, EntityManagerInterface $entityManager, array $blame): void
    {
        $uow = $entityManager->getUnitOfWork();
        foreach ($transaction->getInserted() as $dto) {
            // the changeset might be updated from UOW extra updates
            $ch = array_merge($dto->getChangeset(), $uow->getEntityChangeSet($dto->source));
            $this->insert($entityManager, $dto->source, $ch, $transaction->getTransactionHash(), $blame);
        }
    }

    private function processUpdates(Transaction $transaction, EntityManagerInterface $entityManager, array $blame): void
    {
        $uow = $entityManager->getUnitOfWork();
        foreach ($transaction->getUpdated() as $dto) {
            // the changeset might be updated from UOW extra updates
            $ch = array_merge($dto->getChangeset(), $uow->getEntityChangeSet($dto->source));
            $this->update($entityManager, $dto->source, $ch, $transaction->getTransactionHash(), $blame);
        }
    }

    private function processAssociations(Transaction $transaction, EntityManagerInterface $entityManager, array $blame): void
    {
        foreach ($transaction->getAssociated() as $dto) {
            $this->associate($entityManager, $dto->source, $dto->getTarget(), $dto->getMapping(), $transaction->getTransactionHash(), $blame);
        }
    }

    private function processDissociations(Transaction $transaction, EntityManagerInterface $entityManager, array $blame): void
    {
        foreach ($transaction->getDissociated() as $dto) {
            $this->dissociate($entityManager, $dto->source, $dto->getTarget(), $dto->getMapping(), $transaction->getTransactionHash(), $blame);
        }
    }

    private function processDeletions(Transaction $transaction, EntityManagerInterface $entityManager, array $blame): void
    {
        foreach ($transaction->getRemoved() as $dto) {
            $this->remove($entityManager, $dto->source, $dto->getId(), $transaction->getTransactionHash(), $blame);
        }
    }

    /**
     * Adds an association entry to the audit table.
     */
    private function associateOrDissociate(TransactionType $type, EntityManagerInterface $entityManager, object $source, object $target, array $mapping, string $transactionHash, array $blame): void
    {
        $meta = $entityManager->getClassMetadata(DoctrineHelper::getRealClassName($source));
        $data = [
            'action' => $type,
            'blame' => $blame,
            'diff' => [
                'source' => $this->summarize($entityManager, $source, ['field' => $mapping['fieldName']], $meta),
                'target' => $this->summarize($entityManager, $target, ['field' => $mapping['isOwningSide'] ? $mapping['inversedBy'] : $mapping['mappedBy']]),
                'is_owning_side' => $mapping['isOwningSide'],
            ],
            'table' => $meta->getTableName(),
            'schema' => $meta->getSchemaName(),
            'id' => $this->id($entityManager, $source, $meta),
            'transaction_hash' => $transactionHash,
            'discriminator' => $this->getDiscriminator($source, $meta->inheritanceType),
            'entity' => $meta->getName(),
            'entity_object' => $source,
        ];

        if (isset($mapping['joinTable']['name'])) {
            $data['diff']['table'] = $mapping['joinTable']['name'];
        }

        $this->audit($data);
    }

    /**
     * Adds an entry to the audit table.
     *
     * @param array{action: TransactionType, blame: array<string, mixed>, diff: mixed, table: string, schema: ?string, id: mixed, transaction_hash: string, discriminator: ?string, entity: string, entity_object: ?object} $data
     */
    private function audit(array $data): void
    {
        $entityObject = $data['entity_object'] ?? null;

        /** @var Configuration $configuration */
        $configuration = $this->provider->getConfiguration();
        $schema = $data['schema'] ? $data['schema'].'.' : '';
        $auditTable = $schema.$configuration->getTablePrefix().$data['table'].$configuration->getTableSuffix();
        $tz = $this->dateTimeZone ??= new \DateTimeZone($this->provider->getAuditor()->getConfiguration()->timezone);
        $dt = new \DateTimeImmutable('now', $tz);
        $diff = $data['diff'];
        $convertCharEncoding = (\is_string($diff) || \is_array($diff));
        $diff = $convertCharEncoding ? $this->convertEncoding($diff) : $diff;

        $payload = [
            'entity' => $data['entity'],
            'table' => $auditTable,
            'type' => $data['action']->value,
            'object_id' => (string) $data['id'],
            'discriminator' => $data['discriminator'],
            'transaction_hash' => (string) $data['transaction_hash'],
            'diffs' => json_encode($diff, JSON_THROW_ON_ERROR),
            'extra_data' => null,
            'blame_id' => $data['blame']['user_id'],
            'blame_user' => $data['blame']['username'],
            'blame_user_fqdn' => $data['blame']['user_fqdn'],
            'blame_user_firewall' => $data['blame']['user_firewall'],
            'ip' => $data['blame']['client_ip'],
            'created_at' => $dt->format('Y-m-d H:i:s.u'),
        ];

        // send an `AuditEvent` event
        $this->notify($payload, $entityObject);
    }

    // Avoid warning (and dismissal) of objects in input array when using mb_convert_encoding
    private function convertEncoding(mixed $input): mixed
    {
        if (\is_string($input)) {
            return mb_convert_encoding($input, 'UTF-8', 'UTF-8');
        }

        if (\is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$this->convertEncoding($key)] = $this->convertEncoding($value); // inbuilt mb_convert_encoding also converts keys
            }
        }

        // Leave any other thing as is
        return $input;
    }

    private function getDiscriminator(object $entity, int $inheritanceType): ?string
    {
        return ClassMetadata::INHERITANCE_TYPE_SINGLE_TABLE === $inheritanceType ? DoctrineHelper::getRealClassName($entity) : null;
    }
}
