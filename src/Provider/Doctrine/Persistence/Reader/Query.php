<?php

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Model\Entry;
use DH\Auditor\Provider\ConfigurationInterface;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\DateRangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\FilterInterface;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\RangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Exception;

class Query
{
    public const TYPE = 'type';
    public const CREATED_AT = 'created_at';
    public const TRANSACTION_HASH = 'transaction_hash';
    public const OBJECT_ID = 'object_id';
    public const USER_ID = 'blame_id';
    public const ID = 'id';
    public const DISCRIMINATOR = 'discriminator';

    /**
     * @var array
     */
    private $filters = [];

    /**
     * @var array
     */
    private $orderBy = [];

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $table;

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var int
     */
    private $offset = 0;

    /**
     * @var int
     */
    private $limit = 0;

    public function __construct(string $table, Connection $connection, ConfigurationInterface $configuration)
    {
        $this->connection = $connection;
        $this->table = $table;
        \assert($configuration instanceof Configuration);
        $this->configuration = $configuration;

        foreach ($this->getSupportedFilters() as $filterType) {
            $this->filters[$filterType] = [];
        }
    }

    public function execute(): array
    {
        $queryBuilder = $this->buildQueryBuilder();
        if (method_exists($queryBuilder, 'executeQuery')) {
            // doctrine/dbal v3.x
            $statement = $queryBuilder->executeQuery();
        } else {
            // doctrine/dbal v2.13.x
            $statement = $queryBuilder->execute();
        }

        $result = [];
        \assert($statement instanceof Result);
        foreach ($statement->fetchAllAssociative() as $row) {
            $result[] = Entry::fromArray($row);
        }

        return $result;
    }

    public function count(): int
    {
        $queryBuilder = $this->buildQueryBuilder();

        try {
            $queryBuilder
                ->resetQueryPart('select')
                ->resetQueryPart('orderBy')
                ->setMaxResults(null)
                ->setFirstResult(null)
                ->select('COUNT(id)')
            ;

            if (method_exists($queryBuilder, 'executeQuery')) {
                // doctrine/dbal v3.x
                $result = $queryBuilder
                    ->executeQuery()
                    ->fetchOne()
                ;
            } else {
                // doctrine/dbal v2.13.x
                $result = $queryBuilder
                    ->execute()
                    ->fetchColumn(0)
                ;
            }
        } catch (Exception $e) {
            $result = false;
        }

        return false === $result ? 0 : $result;
    }

    public function addFilter(FilterInterface $filter): self
    {
        $this->checkFilter($filter->getName());
        $this->filters[$filter->getName()][] = $filter;

        return $this;
    }

    public function addOrderBy(string $field, string $direction = 'DESC'): self
    {
        $this->checkFilter($field);

        if (!\in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Invalid sort direction, allowed value: ASC, DESC');
        }

        $this->orderBy[$field] = $direction;

        return $this;
    }

    public function resetOrderBy(): self
    {
        $this->orderBy = [];

        return $this;
    }

    public function limit(int $limit, int $offset = 0): self
    {
        if (0 > $limit) {
            throw new InvalidArgumentException('Limit cannot be negative.');
        }
        if (0 > $offset) {
            throw new InvalidArgumentException('Offset cannot be negative.');
        }

        $this->limit = $limit;
        $this->offset = $offset;

        return $this;
    }

    public function getSupportedFilters(): array
    {
        return array_keys($this->configuration->getAllIndices('fake'));
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    public function getLimit(): array
    {
        return [$this->limit, $this->offset];
    }

    private function buildQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->table, 'at')
        ;

        // build WHERE clause(s)
        $queryBuilder = $this->buildWhere($queryBuilder);

        // build ORDER BY part
        $queryBuilder = $this->buildOrderBy($queryBuilder);

        // build LIMIT part
        return $this->buildLimit($queryBuilder);
    }

    private function groupFilters(array $filters): array
    {
        $grouped = [];

        foreach ($filters as $filter) {
            $class = \get_class($filter);
            if (!isset($grouped[$class])) {
                $grouped[$class] = [];
            }
            $grouped[$class][] = $filter;
        }

        return $grouped;
    }

    private function mergeSimpleFilters(array $filters): SimpleFilter
    {
        $merged = [];
        $name = null;

        foreach ($filters as $filter) {
            if (null === $name) {
                $name = $filter->getName();
            }

            if (\is_array($filter->getValue())) {
                $merged = array_merge($merged, $filter->getValue());
            } else {
                $merged[] = $filter->getValue();
            }
        }

        return new SimpleFilter($name, $merged);
    }

    private function buildWhere(QueryBuilder $queryBuilder): QueryBuilder
    {
        foreach ($this->filters as $name => $rawFilters) {
            if (0 === \count($rawFilters)) {
                continue;
            }

            // group filters by class
            $grouped = $this->groupFilters($rawFilters);

            foreach ($grouped as $class => $filters) {
                switch ($class) {
                    case SimpleFilter::class:
                        $filters = [$this->mergeSimpleFilters($filters)];

                        break;
                    case RangeFilter::class:
                    case DateRangeFilter::class:
                        break;
                }

                foreach ($filters as $filter) {
                    $data = $filter->getSQL();

                    $queryBuilder->andWhere($data['sql']);

                    foreach ($data['params'] as $name => $value) {
                        if (\is_array($value)) {
                            $queryBuilder->setParameter($name, $value, Connection::PARAM_STR_ARRAY);
                        } else {
                            $queryBuilder->setParameter($name, $value);
                        }
                    }
                }
            }
        }

        return $queryBuilder;
    }

    private function buildOrderBy(QueryBuilder $queryBuilder): QueryBuilder
    {
        foreach ($this->orderBy as $field => $direction) {
            $queryBuilder->addOrderBy($field, $direction);
        }

        return $queryBuilder;
    }

    private function buildLimit(QueryBuilder $queryBuilder): QueryBuilder
    {
        if (0 < $this->limit) {
            $queryBuilder->setMaxResults($this->limit);
        }
        if (0 < $this->offset) {
            $queryBuilder->setFirstResult($this->offset);
        }

        return $queryBuilder;
    }

    private function checkFilter(string $filter): void
    {
        if (!\in_array($filter, $this->getSupportedFilters(), true)) {
            throw new InvalidArgumentException(sprintf('Unsupported "%s" filter, allowed filters: %s.', $filter, implode(', ', $this->getSupportedFilters())));
        }
    }
}
