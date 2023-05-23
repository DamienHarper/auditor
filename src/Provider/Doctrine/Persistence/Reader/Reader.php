<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader;

use ArrayIterator;
use DH\Auditor\Exception\AccessDeniedException;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\Security;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;
use DH\Auditor\Provider\Doctrine\Service\AuditingService;
use DH\Auditor\Provider\Doctrine\Service\StorageService;
use Doctrine\ORM\Mapping\ClassMetadata as ORMMetadata;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see \DH\Auditor\Tests\Provider\Doctrine\Persistence\Reader\ReaderTest
 */
class Reader
{
    /**
     * @var int
     */
    public const PAGE_SIZE = 50;

    private DoctrineProvider $provider;

    /**
     * Reader constructor.
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

        $query = new Query($this->getEntityAuditTableName($entity), $storageService->getEntityManager()->getConnection());
        $query
            ->addOrderBy(Query::CREATED_AT, 'DESC')
            ->addOrderBy(Query::ID, 'DESC')
        ;

        if (null !== $config['type']) {
            $query->addFilter(new SimpleFilter(Query::TYPE, $config['type']));
        }

        if (null !== $config['object_id']) {
            $query->addFilter(new SimpleFilter(Query::OBJECT_ID, $config['object_id']));
        }

        if (null !== $config['transaction_hash']) {
            $query->addFilter(new SimpleFilter(Query::TRANSACTION_HASH, $config['transaction_hash']));
        }

        if (null !== $config['page'] && null !== $config['page_size']) {
            $query->limit($config['page_size'], ($config['page'] - 1) * $config['page_size']);
        }

        /** @var AuditingService $auditingService */
        $auditingService = $this->provider->getAuditingServiceForEntity($entity);
        $metadata = $auditingService->getEntityManager()->getClassMetadata($entity);
        if (
            $config['strict']
            && $metadata instanceof ORMMetadata
            && ORMMetadata::INHERITANCE_TYPE_SINGLE_TABLE === $metadata->inheritanceType
        ) {
            $query->addFilter(new SimpleFilter(Query::DISCRIMINATOR, $entity));
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
            ->setAllowedValues('page', static fn (?int $value): bool => null === $value || $value >= 1)
            ->setAllowedValues('page_size', static fn (?int $value): bool => null === $value || $value >= 1)
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
        foreach ($entities as $entity => $entityOptions) {
            try {
                $audits = $this->createQuery($entity, ['transaction_hash' => $transactionHash])->execute();
                if ([] !== $audits) {
                    $results[$entity] = $audits;
                }
            } catch (AccessDeniedException) {
                // acces denied
            }
        }

        return $results;
    }

    /**
     * @return array{results: ArrayIterator<int|string, \DH\Auditor\Model\Entry>, currentPage: int, hasPreviousPage: bool, hasNextPage: bool, previousPage: null|int, nextPage: null|int, numPages: int, haveToPaginate: bool, numResults: int, pageSize: int}
     */
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
            'numResults' => $numResults,
            'pageSize' => $pageSize,
        ];
    }

    /**
     * Returns the table name of $entity.
     */
    public function getEntityTableName(string $entity): string
    {
        /** @var AuditingService $auditingService */
        $auditingService = $this->provider->getAuditingServiceForEntity($entity);

        return $auditingService->getEntityManager()->getClassMetadata($entity)->getTableName();
    }

    /**
     * Returns the audit table name for $entity.
     */
    public function getEntityAuditTableName(string $entity): string
    {
        /** @var Configuration $configuration */
        $configuration = $this->provider->getConfiguration();

        /** @var AuditingService $auditingService */
        $auditingService = $this->provider->getAuditingServiceForEntity($entity);
        $entityManager = $auditingService->getEntityManager();
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
        $roleChecker = $this->provider->getAuditor()->getConfiguration()->getRoleChecker();

        if (null === $roleChecker || $roleChecker($entity, $scope)) {
            return;
        }

        // access denied
        throw new AccessDeniedException('You are not allowed to access audits of "'.$entity.'" entity.');
    }
}
