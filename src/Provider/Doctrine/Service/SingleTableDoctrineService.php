<?php

namespace DH\Auditor\Provider\Doctrine\Service;

use DH\Auditor\Event\LifecycleEvent;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Provider\Service\StorageServiceInterface;

class SingleTableDoctrineService extends DoctrineService implements StorageServiceInterface
{
    /**
     * @var array<string, string>
     */
    private const FIELDS = [
        'type' => '?',
        'object_fqdn' => '?',
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

    public function __construct(DoctrineService $doctrineService, private string $auditTableName = 'audit')
    {
        parent::__construct($doctrineService->getName(), $doctrineService->getEntityManager());
    }

    public function createBaseQuery(Configuration $configuration, string $entity, string $timezone): Query
    {
        $connection = $this->getEntityManager()->getConnection();
        $query = new Query($this->auditTableName, $connection, $timezone, ['id', ...array_keys(self::FIELDS)]);
        $query->addFilter(new SimpleFilter('object_fqdn', $entity));

        return $query;
    }

    public function persist(LifecycleEvent $event): int
    {
        $payload = $event->getPayload();
        $entity = $payload['entity'];
        $payload['object_fqdn'] = $entity;
        unset($payload['table'], $payload['entity']);

        $keys = array_keys(self::FIELDS);
        $query = \sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->auditTableName,
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
}
