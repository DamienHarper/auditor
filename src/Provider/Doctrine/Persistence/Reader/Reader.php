<?php

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader;

use ArrayIterator;
use DH\Auditor\Exception\AccessDeniedException;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\Security;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use DH\Auditor\User\UserInterface;
use Doctrine\ORM\Mapping\ClassMetadata as ORMMetadata;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Reader
{
    public const PAGE_SIZE = 50;

    /**
     * @var DoctrineProvider
     */
    private $provider;

    /**
     * AuditReader constructor.
     */
    public function __construct(DoctrineProvider $provider)
    {
        $this->provider = $provider;
    }

    public function getProvider(): DoctrineProvider
    {
        return $this->provider;
    }

    public function createQuery(string $entity, array $options = []): Query
    {
        $this->checkAuditable($entity);
        $this->checkRoles($entity, Security::VIEW_SCOPE);

        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $config = $resolver->resolve($options);

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity($entity);
        $entityManager = $storageService->getEntityManager();

        $query = new Query($this->getEntityAuditTableName($entity), $entityManager->getConnection());
        $query
            ->addOrderBy(Query::CREATED_AT, 'DESC')
            ->addOrderBy(Query::ID, 'DESC')
        ;

        if (null !== $config['type']) {
            $query->addFilter(Query::TYPE, $config['type']);
        }

        if (null !== $config['object_id']) {
            $query->addFilter(Query::OBJECT_ID, $config['object_id']);
        }

        if (null !== $config['transaction_hash']) {
            $query->addFilter(Query::TRANSACTION_HASH, $config['transaction_hash']);
        }

        if (null !== $config['page'] && null !== $config['page_size']) {
            $query->limit($config['page_size'], ($config['page'] - 1) * $config['page_size']);
        }

        $metadata = $entityManager->getClassMetadata($entity);
        if (
            $config['strict'] &&
            $metadata instanceof ORMMetadata &&
            ORMMetadata::INHERITANCE_TYPE_SINGLE_TABLE === $metadata->inheritanceType
        ) {
            $query->addFilter(Query::DISCRIMINATOR, $entity);
        }

        return $query;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // https://symfony.com/doc/current/components/options_resolver.html
        $resolver
            ->setDefaults([
                'type' => null,
                'object_id' => null,
                'transaction_hash' => null,
                'page' => 1,
                'page_size' => self::PAGE_SIZE,
                'strict' => true,
            ])
            ->setAllowedTypes('type', ['null', 'string', 'array'])
            ->setAllowedTypes('object_id', ['null', 'int', 'string', 'array'])
            ->setAllowedTypes('transaction_hash', ['null', 'string', 'array'])
            ->setAllowedTypes('page', ['null', 'int'])
            ->setAllowedTypes('page_size', ['null', 'int'])
            ->setAllowedTypes('strict', ['null', 'bool'])
            ->setAllowedValues('page', static function ($value) {
                return null === $value || $value >= 1;
            })
            ->setAllowedValues('page_size', static function ($value) {
                return null === $value || $value >= 1;
            })
        ;
    }

    /**
     * Returns an array of all audited entries/operations for a given transaction hash
     * indexed by entity FQCN.
     */
    public function getAuditsByTransactionHash(string $transactionHash): array
    {
        /** @var Configuration $configuration */
        $configuration = $this->provider->getConfiguration();
        $results = [];

        $entities = $configuration->getEntities();
        foreach ($entities as $entity => $tablename) {
            try {
                $audits = $this->createQuery($entity, ['transaction_hash' => $transactionHash])->execute();
                if (\count($audits) > 0) {
                    $results[$entity] = $audits;
                }
            } catch (AccessDeniedException $e) {
                // acces denied
            }
        }

        return $results;
    }

    public function paginate(Query $query, int $page = 1, int $pageSize = self::PAGE_SIZE): array
    {
        $numResults = $query->count();
        $currentPage = $page < 1 ? 1 : $page;
        $hasPreviousPage = $currentPage > 1;
        $hasNextPage = ($currentPage * $pageSize) < $numResults;

        return [
            'results' => new ArrayIterator($query->execute()),
            'currentPage' => $currentPage,
            'hasPreviousPage' => $hasPreviousPage,
            'hasNextPage' => $hasNextPage,
            'previousPage' => $hasPreviousPage ? $currentPage - 1 : null,
            'nextPage' => $hasNextPage ? $currentPage + 1 : null,
            'numPages' => (int) ceil($numResults / $pageSize),
            'haveToPaginate' => $numResults > $pageSize,
        ];
    }

    /**
     * Returns the table name of $entity.
     */
    public function getEntityTableName(string $entity): string
    {
        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity($entity);

        return $storageService->getEntityManager()->getClassMetadata($entity)->getTableName();
    }

    /**
     * Returns the audit table name for $entity.
     */
    public function getEntityAuditTableName(string $entity): string
    {
        /** @var Configuration $configuration */
        $configuration = $this->provider->getConfiguration();

        /** @var StorageService $storageService */
        $storageService = $this->provider->getStorageServiceForEntity($entity);
        $entityManager = $storageService->getEntityManager();
        $schema = '';
        if ($entityManager->getClassMetadata($entity)->getSchemaName()) {
            $schema = $entityManager->getClassMetadata($entity)->getSchemaName().'.';
        }

        return sprintf(
            '%s%s%s%s',
            $schema,
            $configuration->getTablePrefix(),
            $this->getEntityTableName($entity),
            $configuration->getTableSuffix()
        );
    }

    /**
     * Throws an InvalidArgumentException if given entity is not auditable.
     *
     * @throws InvalidArgumentException
     */
    private function checkAuditable(string $entity): void
    {
        if (!$this->provider->isAuditable($entity)) {
            throw new InvalidArgumentException('Entity '.$entity.' is not auditable.');
        }
    }

    /**
     * Throws an AccessDeniedException if user not is granted to access audits for the given entity.
     *
     * @throws AccessDeniedException
     */
    private function checkRoles(string $entity, string $scope): void
    {
        /** @var Configuration $configuration */
        $configuration = $this->provider->getConfiguration();

        $userProvider = $this->provider->getConfiguration()->getUserProvider();
        $user = null === $userProvider ? null : $userProvider->call($this);

        $roleChecker = $this->provider->getConfiguration()->getRoleChecker();
        if (!($user instanceof UserInterface) || null === $roleChecker) {
            // If no security defined or no user identified, consider access granted
            return;
        }

        $entities = $configuration->getEntities();

        $roles = $entities[$entity]['roles'] ?? null;
        if (null === $roles) {
            // If no roles are configured, consider access granted
            return;
        }

        $scope = $roles[$scope] ?? null;
        if (null === $scope) {
            // If no roles for the given scope are configured, consider access granted
            return;
        }

        // roles are defined for the given scope
        foreach ($scope as $role) {
            if ($roleChecker->call($this, $role)) {
                // role granted => access granted
                return;
            }
        }

        // access denied
        throw new AccessDeniedException('You are not allowed to access audits of '.$entity.' entity.');
    }
}
