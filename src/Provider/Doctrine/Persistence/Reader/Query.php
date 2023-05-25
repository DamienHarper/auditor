<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Model\Entry;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\DateRangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\FilterInterface;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\RangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Exception;

/**
 * @see \DH\Auditor\Tests\Provider\Doctrine\Persistence\Reader\QueryTest
 */
class Query
{
    /**
     * @var string
     */
    public const TYPE = 'type';

    /**
     * @var string
     */
    public const CREATED_AT = 'created_at';

    /**
     * @var string
     */
    public const TRANSACTION_HASH = 'transaction_hash';

    /**
     * @var string
     */
    public const OBJECT_ID = 'object_id';

    /**
     * @var string
     */
    public const USER_ID = 'blame_id';

    /**
     * @var string
     */
    public const ID = 'id';

    /**
     * @var string
     */
    public const DISCRIMINATOR = 'discriminator';

    private array $filters = [];

    private array $orderBy = [];

    private Connection $connection;

    private string $table;

    private int $offset = 0;

    private int $limit = 0;

    public function __construct(string $table, Connection $connection)
    {
        $this->connection = $connection;
        $this->table = $table;

        foreach ($this->getSupportedFilters() as $filterType) {
            $this->filters[$filterType] = [];
        }
    }

    /**
     * @return array<Entry>
     */
    public function execute(): array
    {
        $queryBuilder = $this->buildQueryBuilder();
        $statement = $queryBuilder->executeQuery();

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
                ->setFirstResult(0)
                ->select('COUNT(id)')
            ;

            /** @var false|int $result */
            $result = $queryBuilder->executeQuery()->fetchOne();
        } catch (Exception) {
            $result = false;
        }

        return (int) $result;
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
        return array_keys(SchemaHelper::getAuditTableIndices('fake'));
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    /**
     * @return array<int>
     */
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
            if (!isset($grouped[$filter::class])) {
                $grouped[$filter::class] = [];
            }

            $grouped[$filter::class][] = $filter;
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
            if (0 === (is_countable($rawFilters) ? \count($rawFilters) : 0)) {
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
