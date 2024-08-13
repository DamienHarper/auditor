<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Reader;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\DateRangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\RangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ConnectionTrait;
use DH\Auditor\Tests\Traits\ReflectionTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
#[CoversNothing]
final class QueryTest extends TestCase
{
    use ConnectionTrait;
    use ReflectionTrait;

    public function testNoFiltersByDefault(): void
    {
        $query = new Query('author_audit', $this->createConnection(), 'UTC');

        $filters = $query->getFilters();
        foreach ($filters as $values) {
            $this->assertSame([], $values, 'No filter by default.');
        }
    }

    #[Depends('testNoFiltersByDefault')]
    public function testAddSimpleFilter(): void
    {
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $filter1 = new SimpleFilter(Query::TRANSACTION_HASH, '123abc');
        $query->addFilter($filter1);

        $filters = $query->getFilters();
        $this->assertCount(1, $filters[Query::TRANSACTION_HASH], 'Filter is added.');
        $this->assertSame([$filter1], $filters[Query::TRANSACTION_HASH], 'Filter is added.');

        $filter2 = new SimpleFilter(Query::TRANSACTION_HASH, '456def');
        $query->addFilter($filter2);

        $filters = $query->getFilters();
        $this->assertCount(2, $filters[Query::TRANSACTION_HASH], 'Filter is added.');
        $this->assertSame([$filter1, $filter2], $filters[Query::TRANSACTION_HASH], 'Second filter is added.');

        $filter3 = new SimpleFilter(Query::TRANSACTION_HASH, ['789ghi', '012jkl']);
        $query->addFilter($filter3);

        $filters = $query->getFilters();
        $this->assertCount(3, $filters[Query::TRANSACTION_HASH], 'Filter is added.');
        $this->assertSame([$filter1, $filter2, $filter3], $filters[Query::TRANSACTION_HASH], 'Second filter is added.');
    }

    #[Depends('testAddSimpleFilter')]
    public function testAddUnexpectedFilter(): void
    {
        $query = new Query('author_audit', $this->createConnection(), 'UTC');

        $this->expectException(InvalidArgumentException::class);

        $query->addFilter(new SimpleFilter('unknown_filter', '123abc'));
    }

    #[Depends('testAddUnexpectedFilter')]
    public function testAddRangeFilter(): void
    {
        // only min bound
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $filter = new RangeFilter(Query::OBJECT_ID, 1);
        $query->addFilter($filter);

        $filters = $query->getFilters();
        $this->assertSame([$filter], $filters[Query::OBJECT_ID], 'Range filter with min bound only is added.');

        // only max bound
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $filter = new RangeFilter(Query::OBJECT_ID, null, 1);
        $query->addFilter($filter);

        $filters = $query->getFilters();
        $this->assertSame([$filter], $filters[Query::OBJECT_ID], 'Range filter with max bound only is added.');

        // min and max bound
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $filter = new RangeFilter(Query::OBJECT_ID, 5, 15);
        $query->addFilter($filter);

        $filters = $query->getFilters();
        $this->assertSame([$filter], $filters[Query::OBJECT_ID], 'Range filter with both bound is added.');
    }

    #[Depends('testAddUnexpectedFilter')]
    public function testAddDateRangeFilter(): void
    {
        $min = new \DateTimeImmutable('-1 day');
        $max = new \DateTimeImmutable('+1 day');

        // only min bound
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $filter = new DateRangeFilter(Query::CREATED_AT, $min);
        $query->addFilter($filter);

        $filters = $query->getFilters();
        $this->assertSame([$filter], $filters[Query::CREATED_AT], 'Date range filter with min bound only is added.');

        // only max bound
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $filter = new DateRangeFilter(Query::CREATED_AT, null, $max);
        $query->addFilter($filter);

        $filters = $query->getFilters();
        $this->assertSame([$filter], $filters[Query::CREATED_AT], 'Date range filter with max bound only is added.');

        // min and max bound
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $filter = new DateRangeFilter(Query::CREATED_AT, $min, $max);
        $query->addFilter($filter);

        $filters = $query->getFilters();
        $this->assertSame([$filter], $filters[Query::CREATED_AT], 'Date range filter with both bound is added.');
    }

    public function testNoOrderByByDefault(): void
    {
        $query = new Query('author_audit', $this->createConnection(), 'UTC');

        $this->assertSame([], $query->getOrderBy(), 'No ORDER BY by default.');
    }

    #[Depends('testNoOrderByByDefault')]
    public function testAddOrderBy(): void
    {
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $query->addOrderBy(Query::TRANSACTION_HASH, 'ASC');

        $orderBy = $query->getOrderBy();
        $this->assertSame('ASC', $orderBy[Query::TRANSACTION_HASH], 'ORDER BY is added.');

        $query->addOrderBy(Query::TRANSACTION_HASH, 'DESC');
        $orderBy = $query->getOrderBy();
        $this->assertSame('DESC', $orderBy[Query::TRANSACTION_HASH], 'ORDER BY is overwritten.');

        $query->addOrderBy(Query::OBJECT_ID, 'ASC');
        $orderBy = $query->getOrderBy();

        $expected = [
            Query::TRANSACTION_HASH => 'DESC',
            Query::OBJECT_ID => 'ASC',
        ];

        $this->assertSame($expected, $orderBy, 'Second ORDER BY is added.');
    }

    #[Depends('testAddOrderBy')]
    public function testAddUnexpectedOrderBy(): void
    {
        $query = new Query('author_audit', $this->createConnection(), 'UTC');

        $this->expectException(InvalidArgumentException::class);

        $query->addOrderBy(Query::TRANSACTION_HASH, 'unknown');
    }

    public function testNoLimitNoOffsetByDefault(): void
    {
        $query = new Query('author_audit', $this->createConnection(), 'UTC');

        $this->assertSame([0, 0], $query->getLimit(), 'No LIMIT by default.');
    }

    #[Depends('testNoLimitNoOffsetByDefault')]
    public function testLimitWithoutOffset(): void
    {
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $query->limit(10);

        $this->assertSame([10, 0], $query->getLimit(), 'LIMIT without offset is OK.');
    }

    #[Depends('testNoLimitNoOffsetByDefault')]
    public function testWithLimitAndOffset(): void
    {
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $query->limit(10, 50);

        $this->assertSame([10, 50], $query->getLimit(), 'LIMIT with offset is OK.');
    }

    #[Depends('testNoLimitNoOffsetByDefault')]
    public function testLimitNegativeLimit(): void
    {
        $query = new Query('author_audit', $this->createConnection(), 'UTC');

        $this->expectException(InvalidArgumentException::class);
        $query->limit(-1, 50);
    }

    #[Depends('testNoLimitNoOffsetByDefault')]
    public function testLimitNegativeOffset(): void
    {
        $query = new Query('author_audit', $this->createConnection(), 'UTC');

        $this->expectException(InvalidArgumentException::class);
        $query->limit(0, -50);
    }

    #[Depends('testAddSimpleFilter')]
    #[Depends('testAddOrderBy')]
    public function testBuildQueryBuilderDefault(): void
    {
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);

        // test default SQL query
        $expectedQuery = 'SELECT * FROM author_audit at';
        $expectedParameters = [];
        $this->assertSame($expectedQuery, $queryBuilder->getSQL(), 'Default SQL query is OK.');
        $this->assertSame($expectedParameters, $queryBuilder->getParameters(), 'No parameters if no filters.');
    }

    #[Depends('testBuildQueryBuilderDefault')]
    public function testBuildQueryBuilderSimpleFilter(): void
    {
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        // test SQL query with 1 filter
        $expectedQuery = 'SELECT * FROM author_audit at WHERE transaction_hash = :transaction_hash';
        $expectedParameters = [
            'transaction_hash' => '123abc',
        ];
        $query->addFilter(new SimpleFilter(Query::TRANSACTION_HASH, '123abc'));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        $this->assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with 1 filter.');
        $this->assertSame($expectedParameters, $queryBuilder->getParameters(), 'Parameters OK with 1 filter.');

        // test SQL query with 2 filters
        $expectedQuery = 'SELECT * FROM author_audit at WHERE transaction_hash IN (:transaction_hash)';
        $expectedParameters = [
            'transaction_hash' => ['123abc', '456def'],
        ];
        $query->addFilter(new SimpleFilter(Query::TRANSACTION_HASH, '456def'));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        $this->assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with 2 filters.');
        $this->assertSame($expectedParameters, $queryBuilder->getParameters(), 'Parameters OK with 2 filters.');

        // test SQL query with 3 filters
        $expectedQuery = 'SELECT * FROM author_audit at WHERE transaction_hash IN (:transaction_hash)';
        $expectedParameters = [
            'transaction_hash' => ['123abc', '456def', '789ghj', '012jkl'],
        ];
        $query->addFilter(new SimpleFilter(Query::TRANSACTION_HASH, ['789ghj', '012jkl']));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        $this->assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with 3 filters.');
        $this->assertSame($expectedParameters, $queryBuilder->getParameters(), 'Parameters OK with 3 filters.');
    }

    #[Depends('testBuildQueryBuilderDefault')]
    public function testBuildQueryBuilderOrderBy(): void
    {
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        // test SQL query with 1 ORDER BY
        $expectedQuery = 'SELECT * FROM author_audit at ORDER BY created_at DESC';
        $query->addOrderBy(Query::CREATED_AT, 'DESC');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        $this->assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with 1 ORDER BY.');

        // test SQL query with 2 ORDER BY
        $expectedQuery = 'SELECT * FROM author_audit at ORDER BY created_at DESC, id DESC';
        $query->addOrderBy(Query::ID, 'DESC');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        $this->assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with 2 ORDER BY.');
    }

    #[Depends('testBuildQueryBuilderDefault')]
    public function testBuildQueryBuilderLimit(): void
    {
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        // test SQL query with LIMIT
        $expectedQuery = 'SELECT * FROM author_audit at LIMIT 10';
        $query->limit(10);
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        $this->assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with LIMIT.');

        // test SQL query with LIMIT
        $expectedQuery = 'SELECT * FROM author_audit at LIMIT 10 OFFSET 50';
        $query->limit(10, 50);
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        $this->assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with LIMIT.');
    }

    #[Depends('testBuildQueryBuilderDefault')]
    public function testBuildQueryBuilderRangeFilter(): void
    {
        // test SQL query with a range filter, min bound only
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        $expectedQuery = 'SELECT * FROM author_audit at WHERE object_id >= :min_object_id';
        $query->addFilter(new RangeFilter(Query::OBJECT_ID, 5));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        $this->assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with min bound only.');

        // test SQL query with a range filter, max bound only
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        $expectedQuery = 'SELECT * FROM author_audit at WHERE object_id <= :max_object_id';
        $query->addFilter(new RangeFilter(Query::OBJECT_ID, null, 25));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        $this->assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with max bound only.');

        // test SQL query with a range filter with both bounds
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        $expectedQuery = 'SELECT * FROM author_audit at WHERE object_id >= :min_object_id AND object_id <= :max_object_id';
        $query->addFilter(new RangeFilter(Query::OBJECT_ID, 5, 25));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        $this->assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with max bound only.');
    }

    #[Depends('testBuildQueryBuilderDefault')]
    public function testBuildQueryBuilderDateRangeFilter(): void
    {
        $min = new \DateTimeImmutable('-1 day');
        $max = new \DateTimeImmutable('+1 day');

        // test SQL query with a date range filter, min bound only
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        $expectedQuery = 'SELECT * FROM author_audit at WHERE object_id >= :min_object_id';
        $query->addFilter(new RangeFilter(Query::OBJECT_ID, $min->format('Y-m-d H:i:s')));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        $this->assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with min bound only.');

        // test SQL query with a date range filter, max bound only
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        $expectedQuery = 'SELECT * FROM author_audit at WHERE object_id <= :max_object_id';
        $query->addFilter(new RangeFilter(Query::OBJECT_ID, null, $max->format('Y-m-d H:i:s')));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        $this->assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with max bound only.');

        // test SQL query with a date range filter with both bounds
        $query = new Query('author_audit', $this->createConnection(), 'UTC');
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        $expectedQuery = 'SELECT * FROM author_audit at WHERE object_id >= :min_object_id AND object_id <= :max_object_id';
        $query->addFilter(new RangeFilter(Query::OBJECT_ID, $min->format('Y-m-d H:i:s'), $max->format('Y-m-d H:i:s')));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        $this->assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with max bound only.');
    }
}
