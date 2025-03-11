<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Service;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Provider\Service\AbstractService;
use Doctrine\ORM\EntityManagerInterface;

abstract class DoctrineService extends AbstractService
{
    /**
     * @var array<string, string>
     */
    private const FIELDS = [
        'type' => '?',
        'object_id' => '?',
        'discriminator' => '?',
        'transaction_hash' => '?',
        'diffs' => '?',
        'blame_id' => '?',
        'blame_user' => '?',
        'blame_user_fqdn' => '?',
        'blame_user_firewall' => '?',
        'ip' => '?',
        'created_at' => '?',
    ];

    public function __construct(string $name, private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct($name);
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    public function createBaseQuery(Configuration $configuration, string $entity, string $timezone): Query
    {
        $connection = $this->getEntityManager()->getConnection();

        return new Query($this->getEntityAuditTableName($configuration, $entity), $connection, $timezone, ['id', ...array_keys(self::FIELDS)]);
    }

    public function persist(LifecycleEvent $event): int
    {
        $payload = $event->getPayload();
        $auditTable = $payload['table'];
        unset($payload['table'], $payload['entity']);

        $keys = array_keys(self::FIELDS);
        $query = \sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $auditTable,
            implode(', ', $keys),
            implode(', ', array_values(self::FIELDS))
        );

        $statement = $this->getEntityManager()->getConnection()->prepare($query);

        foreach ($payload as $key => $value) {
            $statement->bindValue(array_search($key, $keys, true) + 1, $value);
        }

        $statement->executeStatement();

        return (int) $this->getEntityManager()->getConnection()->lastInsertId();
    }

    /**
     * Returns the audit table name for $entity.
     */
    public function getEntityAuditTableName(Configuration $configuration, string $entity): string
    {
        $schema = '';
        if ($this->entityManager->getClassMetadata($entity)->getSchemaName()) {
            $schema = $this->entityManager->getClassMetadata($entity)->getSchemaName().'.';
        }

        return \sprintf(
            '%s%s%s%s',
            $schema,
            $configuration->getTablePrefix(),
            $this->entityManager->getClassMetadata($entity)->getTableName(),
            $configuration->getTableSuffix()
        );
    }
}
