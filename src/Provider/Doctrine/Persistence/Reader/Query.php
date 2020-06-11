<?php

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader;

use DateTime;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Model\Entry;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Exception;
use PDO;

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
     * @var int
     */
    private $offset = 0;

    /**
     * @var int
     */
    private $limit = 0;

    public function __construct(string $table, Connection $connection)
    {
        $this->connection = $connection;
        $this->table = $table;

        foreach ($this->getSupportedFilters() as $filterType) {
            $this->filters[$filterType] = [];
        }
    }

    public function execute(): array
    {
        $queryBuilder = $this->buildQueryBuilder();
        $statement = $queryBuilder->execute();
        $statement->setFetchMode(PDO::FETCH_CLASS, Entry::class);

        return $statement->fetchAll();
    }

    public function count(): int
    {
        $queryBuilder = $this->buildQueryBuilder();

        try {
            $result = $queryBuilder
                ->resetQueryPart('select')
                ->resetQueryPart('orderBy')
                ->setMaxResults(null)
                ->setFirstResult(null)
                ->select('COUNT(id)')
                ->execute()
                ->fetchColumn(0)
            ;
        } catch (Exception $e) {
            $result = false;
        }

        return false === $result ? 0 : $result;
    }

    /**
     * @param mixed $value
     */
    public function addFilter(string $name, $value): self
    {
        $this->checkFilter($name);

        $this->filters[$name][] = $value;

        return $this;
    }

    /**
     * @param mixed $minValue
     * @param mixed $maxValue
     */
    public function addRangeFilter(string $name, $minValue = null, $maxValue = null): self
    {
        $this->checkFilter($name);

        if (null === $minValue && null === $maxValue) {
            throw new InvalidArgumentException('You must provide at least one of the two range bounds.');
        }

        $this->filters[$name][] = [$minValue, $maxValue];

        return $this;
    }

    public function addDateRangeFilter(string $name, ?DateTime $minValue = null, ?DateTime $maxValue = null): self
    {
        $this->checkFilter($name);

        if (null === $minValue && null === $maxValue) {
            throw new InvalidArgumentException('You must provide at least one of the two range bounds.');
        }

        if (null !== $minValue && null !== $maxValue && $minValue > $maxValue) {
            throw new InvalidArgumentException('Max bound has to be later than min bound.');
        }

        $this->filters[$name][] = [
            null === $minValue ? null : $minValue->format('Y-m-d H:i:s'),
            null === $maxValue ? null : $maxValue->format('Y-m-d H:i:s'),
        ];

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

    private function buildWhere(QueryBuilder $queryBuilder): QueryBuilder
    {
        foreach ($this->filters as $filter => $values) {
            if (empty($values)) {
                continue;
            }

            if (1 === \count($values) && \is_array($values[0])) {
                // Range filter
                if (null !== $values[0][0]) {
                    $queryBuilder
                        ->andWhere(sprintf('%s >= :min_%s', $filter, $filter))
                        ->setParameter('min_'.$filter, $values[0][0])
                    ;
                }
                if (null !== $values[0][1]) {
                    $queryBuilder
                        ->andWhere(sprintf('%s <= :max_%s', $filter, $filter))
                        ->setParameter('max_'.$filter, $values[0][1])
                    ;
                }
            } elseif (1 === \count($values) && !\is_array($values[0])) {
                $queryBuilder
                    ->andWhere(sprintf('%s = :%s', $filter, $filter))
                    ->setParameter($filter, $values[0])
                ;
            } else {
                $queryBuilder
                    ->andWhere(sprintf('%s IN (:%s)', $filter, $filter))
                    ->setParameter($filter, $values)
                ;
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
