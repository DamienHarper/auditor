<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Reader;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ConnectionTrait;
use DH\Auditor\Tests\Traits\ReflectionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class QueryTest extends TestCase
{
    use ConnectionTrait;
    use ReflectionTrait;

    public function testNoFiltersByDefault(): void
    {
        $query = new Query('author_audit', $this->createConnection());

        $filters = $query->getFilters();
        foreach ($filters as $filter => $values) {
            self::assertSame([], $values, 'No filter by default.');
        }
    }

    /**
     * @depends testNoFiltersByDefault
     */
    public function testAddFilter(): void
    {
        $query = new Query('author_audit', $this->createConnection());
        $query->addFilter(Query::TRANSACTION_HASH, '123abc');

        $filters = $query->getFilters();
        self::assertSame(['123abc'], $filters[Query::TRANSACTION_HASH], 'Filter is added.');

        $query->addFilter(Query::TRANSACTION_HASH, '456def');

        $filters = $query->getFilters();
        self::assertSame(['123abc', '456def'], $filters[Query::TRANSACTION_HASH], 'Second filter is added.');
    }

    /**
     * @depends testAddFilter
     */
    public function testAddUnexpectedFilter(): void
    {
        $query = new Query('author_audit', $this->createConnection());

        $this->expectException(InvalidArgumentException::class);

        $query->addFilter('unknown_filter', '123abc');
    }

    /**
     * @depends testAddUnexpectedFilter
     */
    public function testAddRangeFilter(): void
    {
        // only min bound
        $query = new Query('author_audit', $this->createConnection());
        $query->addRangeFilter(Query::OBJECT_ID, 1);
        $filters = $query->getFilters();
        self::assertSame([[1, null]], $filters[Query::OBJECT_ID], 'Range filter with min bound only is added.');

        // only max bound
        $query = new Query('author_audit', $this->createConnection());
        $query->addRangeFilter(Query::OBJECT_ID, null, 1);
        $filters = $query->getFilters();
        self::assertSame([[null, 1]], $filters[Query::OBJECT_ID], 'Range filter with max bound only is added.');

        // min and max bound
        $query = new Query('author_audit', $this->createConnection());
        $query->addRangeFilter(Query::OBJECT_ID, 5, 15);
        $filters = $query->getFilters();
        self::assertSame([[5, 15]], $filters[Query::OBJECT_ID], 'Range filter with both bound is added.');

        $this->expectException(InvalidArgumentException::class);
        $query->addRangeFilter(Query::OBJECT_ID);
    }

    /**
     * @depends testAddUnexpectedFilter
     */
    public function testAddDateRangeFilter(): void
    {
        $min = new \DateTime('-1 day');
        $max = new \DateTime('+1 day');

        // only min bound
        $query = new Query('author_audit', $this->createConnection());
        $query->addDateRangeFilter(Query::CREATED_AT, $min);
        $filters = $query->getFilters();
        self::assertSame([[$min->format('Y-m-d H:i:s'), null]], $filters[Query::CREATED_AT], 'Date range filter with min bound only is added.');

        // only max bound
        $query = new Query('author_audit', $this->createConnection());
        $query->addDateRangeFilter(Query::CREATED_AT, null, $max);
        $filters = $query->getFilters();
        self::assertSame([[null, $max->format('Y-m-d H:i:s')]], $filters[Query::CREATED_AT], 'Date range filter with max bound only is added.');

        // min and max bound
        $query = new Query('author_audit', $this->createConnection());
        $query->addDateRangeFilter(Query::CREATED_AT, $min, $max);
        $filters = $query->getFilters();
        self::assertSame([[$min->format('Y-m-d H:i:s'), $max->format('Y-m-d H:i:s')]], $filters[Query::CREATED_AT], 'Date range filter with both bound is added.');

        $this->expectException(InvalidArgumentException::class);
        $query->addDateRangeFilter(Query::CREATED_AT);
    }

    public function testNoOrderByByDefault(): void
    {
        $query = new Query('author_audit', $this->createConnection());

        self::assertSame([], $query->getOrderBy(), 'No ORDER BY by default.');
    }

    /**
     * @depends testNoOrderByByDefault
     */
    public function testAddOrderBy(): void
    {
        $query = new Query('author_audit', $this->createConnection());
        $query->addOrderBy(Query::TRANSACTION_HASH, 'ASC');

        $orderBy = $query->getOrderBy();
        self::assertSame('ASC', $orderBy[Query::TRANSACTION_HASH], 'ORDER BY is added.');

        $query->addOrderBy(Query::TRANSACTION_HASH, 'DESC');
        $orderBy = $query->getOrderBy();
        self::assertSame('DESC', $orderBy[Query::TRANSACTION_HASH], 'ORDER BY is overwritten.');

        $query->addOrderBy(Query::OBJECT_ID, 'ASC');
        $orderBy = $query->getOrderBy();

        $expected = [
            Query::TRANSACTION_HASH => 'DESC',
            Query::OBJECT_ID => 'ASC',
        ];

        self::assertSame($expected, $orderBy, 'Second ORDER BY is added.');
    }

    /**
     * @depends testAddOrderBy
     */
    public function testAddUnexpectedOrderBy(): void
    {
        $query = new Query('author_audit', $this->createConnection());

        $this->expectException(InvalidArgumentException::class);

        $query->addOrderBy(Query::TRANSACTION_HASH, 'unknown');
    }

    public function testNoLimitNoOffsetByDefault(): void
    {
        $query = new Query('author_audit', $this->createConnection());

        self::assertSame([0, 0], $query->getLimit(), 'No LIMIT by default.');
    }

    /**
     * @depends testNoLimitNoOffsetByDefault
     */
    public function testLimitWithoutOffset(): void
    {
        $query = new Query('author_audit', $this->createConnection());
        $query->limit(10);

        self::assertSame([10, 0], $query->getLimit(), 'LIMIT without offset is OK.');
    }

    /**
     * @depends testNoLimitNoOffsetByDefault
     */
    public function testWithLimitAndOffset(): void
    {
        $query = new Query('author_audit', $this->createConnection());
        $query->limit(10, 50);

        self::assertSame([10, 50], $query->getLimit(), 'LIMIT with offset is OK.');
    }

    /**
     * @depends testNoLimitNoOffsetByDefault
     */
    public function testLimitNegativeLimit(): void
    {
        $query = new Query('author_audit', $this->createConnection());

        $this->expectException(InvalidArgumentException::class);
        $query->limit(-1, 50);
    }

    /**
     * @depends testNoLimitNoOffsetByDefault
     */
    public function testLimitNegativeOffset(): void
    {
        $query = new Query('author_audit', $this->createConnection());

        $this->expectException(InvalidArgumentException::class);
        $query->limit(0, -50);
    }

    /**
     * @depends testAddFilter
     * @depends testAddOrderBy
     */
    public function testBuildQueryBuilderDefault(): void
    {
        $query = new Query('author_audit', $this->createConnection());
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);

        // test default SQL query
        $expectedQuery = 'SELECT * FROM author_audit at';
        $expectedParameters = [];
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'Default SQL query is OK.');
        self::assertSame($expectedParameters, $queryBuilder->getParameters(), 'No parameters if no filters.');
    }

    /**
     * @depends testBuildQueryBuilderDefault
     */
    public function testBuildQueryBuilderSimpleFilter(): void
    {
        $query = new Query('author_audit', $this->createConnection());
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);

        // test SQL query with 1 filter
        $expectedQuery = 'SELECT * FROM author_audit at WHERE transaction_hash = :transaction_hash';
        $expectedParameters = [
            'transaction_hash' => '123abc',
        ];
        $query->addFilter(Query::TRANSACTION_HASH, '123abc');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with 1 filter.');
        self::assertSame($expectedParameters, $queryBuilder->getParameters(), 'Parameters OK with 1 filter.');

        // test SQL query with 2 filters
        $expectedQuery = 'SELECT * FROM author_audit at WHERE transaction_hash IN (:transaction_hash)';
        $expectedParameters = [
            'transaction_hash' => ['123abc', '456def'],
        ];
        $query->addFilter(Query::TRANSACTION_HASH, '456def');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with 1 filter.');
        self::assertSame($expectedParameters, $queryBuilder->getParameters(), 'Parameters OK with 2 filters.');
    }

    /**
     * @depends testBuildQueryBuilderDefault
     */
    public function testBuildQueryBuilderOrderBy(): void
    {
        $query = new Query('author_audit', $this->createConnection());
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);

        // test SQL query with 1 ORDER BY
        $expectedQuery = 'SELECT * FROM author_audit at ORDER BY created_at DESC';
        $query->addOrderBy(Query::CREATED_AT, 'DESC');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with 1 ORDER BY.');

        // test SQL query with 2 ORDER BY
        $expectedQuery = 'SELECT * FROM author_audit at ORDER BY created_at DESC, id DESC';
        $query->addOrderBy(Query::ID, 'DESC');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with 2 ORDER BY.');
    }

    /**
     * @depends testBuildQueryBuilderDefault
     */
    public function testBuildQueryBuilderLimit(): void
    {
        $query = new Query('author_audit', $this->createConnection());
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);

        // test SQL query with LIMIT
        $expectedQuery = 'SELECT * FROM author_audit at LIMIT 10';
        $query->limit(10);
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with LIMIT.');

        // test SQL query with LIMIT
        $expectedQuery = 'SELECT * FROM author_audit at LIMIT 10 OFFSET 50';
        $query->limit(10, 50);
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with LIMIT.');
    }

    /**
     * @depends testBuildQueryBuilderDefault
     */
    public function testBuildQueryBuilderRangeFilter(): void
    {
        // test SQL query with a range filter, min bound only
        $query = new Query('author_audit', $this->createConnection());
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);

        $expectedQuery = 'SELECT * FROM author_audit at WHERE object_id >= :min_object_id';
        $query->addRangeFilter(Query::OBJECT_ID, 5);
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with min bound only.');

        // test SQL query with a range filter, max bound only
        $query = new Query('author_audit', $this->createConnection());
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);

        $expectedQuery = 'SELECT * FROM author_audit at WHERE object_id <= :max_object_id';
        $query->addRangeFilter(Query::OBJECT_ID, null, 25);
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with max bound only.');

        // test SQL query with a range filter with both bounds
        $query = new Query('author_audit', $this->createConnection());
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);

        $expectedQuery = 'SELECT * FROM author_audit at WHERE (object_id >= :min_object_id) AND (object_id <= :max_object_id)';
        $query->addRangeFilter(Query::OBJECT_ID, 5, 25);
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with max bound only.');
    }

    /**
     * @depends testBuildQueryBuilderDefault
     */
    public function testBuildQueryBuilderDateRangeFilter(): void
    {
        $min = new \DateTime('-1 day');
        $max = new \DateTime('+1 day');

        // test SQL query with a date range filter, min bound only
        $query = new Query('author_audit', $this->createConnection());
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);

        $expectedQuery = 'SELECT * FROM author_audit at WHERE object_id >= :min_object_id';
        $query->addRangeFilter(Query::OBJECT_ID, $min->format('Y-m-d H:i:s'));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with min bound only.');

        // test SQL query with a date range filter, max bound only
        $query = new Query('author_audit', $this->createConnection());
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);

        $expectedQuery = 'SELECT * FROM author_audit at WHERE object_id <= :max_object_id';
        $query->addRangeFilter(Query::OBJECT_ID, null, $max->format('Y-m-d H:i:s'));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with max bound only.');

        // test SQL query with a date range filter with both bounds
        $query = new Query('author_audit', $this->createConnection());
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);

        $expectedQuery = 'SELECT * FROM author_audit at WHERE (object_id >= :min_object_id) AND (object_id <= :max_object_id)';
        $query->addRangeFilter(Query::OBJECT_ID, $min->format('Y-m-d H:i:s'), $max->format('Y-m-d H:i:s'));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with max bound only.');
    }
}
