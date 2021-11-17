<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Reader;

use DateTime;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\DateRangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\RangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ConnectionTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ProviderConfigurationTrait;
use DH\Auditor\Tests\Traits\ReflectionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class QueryTest extends TestCase
{
    use ConnectionTrait;
    use ReflectionTrait;
    use ProviderConfigurationTrait;

    public function testNoFiltersByDefault(): void
    {
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));

        $filters = $query->getFilters();
        foreach ($filters as $filter => $values) {
            self::assertSame([], $values, 'No filter by default.');
        }
    }

    /**
     * @depends testNoFiltersByDefault
     */
    public function testAddSimpleFilter(): void
    {
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $filter1 = new SimpleFilter(Query::TRANSACTION_HASH, '123abc');
        $query->addFilter($filter1);

        $filters = $query->getFilters();
        self::assertCount(1, $filters[Query::TRANSACTION_HASH], 'Filter is added.');
        self::assertSame([$filter1], $filters[Query::TRANSACTION_HASH], 'Filter is added.');

        $filter2 = new SimpleFilter(Query::TRANSACTION_HASH, '456def');
        $query->addFilter($filter2);

        $filters = $query->getFilters();
        self::assertCount(2, $filters[Query::TRANSACTION_HASH], 'Filter is added.');
        self::assertSame([$filter1, $filter2], $filters[Query::TRANSACTION_HASH], 'Second filter is added.');

        $filter3 = new SimpleFilter(Query::TRANSACTION_HASH, ['789ghi', '012jkl']);
        $query->addFilter($filter3);

        $filters = $query->getFilters();
        self::assertCount(3, $filters[Query::TRANSACTION_HASH], 'Filter is added.');
        self::assertSame([$filter1, $filter2, $filter3], $filters[Query::TRANSACTION_HASH], 'Second filter is added.');
    }

    /**
     * @depends testAddSimpleFilter
     */
    public function testAddExtraIndexFilter(): void
    {
        $query = new Query(
            'author_audit',
            $this->createConnection(),
            $this->createProviderConfiguration([
                'extra_fields' => [
                    'extra_column' => [
                        'type' => 'string',
                        'options' => ['notnull' => true]
                    ]
                ],
                'extra_indices' => ['extra_column' => null]
            ])
        );
        $filter1 = new SimpleFilter('extra_column', '123abc');
        $query->addFilter($filter1);

        $filters = $query->getFilters();
        self::assertCount(1, $filters['extra_column'], 'Filter is added.');
        self::assertSame([$filter1], $filters['extra_column'], 'Filter is added.');

        $filter2 = new SimpleFilter('extra_column', '456def');
        $query->addFilter($filter2);

        $filters = $query->getFilters();
        self::assertCount(2, $filters['extra_column'], 'Filter is added.');
        self::assertSame([$filter1, $filter2], $filters['extra_column'], 'Second filter is added.');

        $filter3 = new SimpleFilter('extra_column', ['789ghi', '012jkl']);
        $query->addFilter($filter3);

        $filters = $query->getFilters();
        self::assertCount(3, $filters['extra_column'], 'Filter is added.');
        self::assertSame([$filter1, $filter2, $filter3], $filters['extra_column'], 'Second filter is added.');
    }

    /**
     * @depends testAddSimpleFilter
     */
    public function testAddUnexpectedFilter(): void
    {
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));

        $this->expectException(InvalidArgumentException::class);

        $query->addFilter(new SimpleFilter('unknown_filter', '123abc'));
    }

    /**
     * @depends testAddUnexpectedFilter
     */
    public function testAddRangeFilter(): void
    {
        // only min bound
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $filter = new RangeFilter(Query::OBJECT_ID, 1);
        $query->addFilter($filter);

        $filters = $query->getFilters();
        self::assertSame([$filter], $filters[Query::OBJECT_ID], 'Range filter with min bound only is added.');

        // only max bound
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $filter = new RangeFilter(Query::OBJECT_ID, null, 1);
        $query->addFilter($filter);

        $filters = $query->getFilters();
        self::assertSame([$filter], $filters[Query::OBJECT_ID], 'Range filter with max bound only is added.');

        // min and max bound
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $filter = new RangeFilter(Query::OBJECT_ID, 5, 15);
        $query->addFilter($filter);

        $filters = $query->getFilters();
        self::assertSame([$filter], $filters[Query::OBJECT_ID], 'Range filter with both bound is added.');
    }

    /**
     * @depends testAddUnexpectedFilter
     */
    public function testAddDateRangeFilter(): void
    {
        $min = new DateTime('-1 day');
        $max = new DateTime('+1 day');

        // only min bound
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $filter = new DateRangeFilter(Query::CREATED_AT, $min);
        $query->addFilter($filter);

        $filters = $query->getFilters();
        self::assertSame([$filter], $filters[Query::CREATED_AT], 'Date range filter with min bound only is added.');

        // only max bound
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $filter = new DateRangeFilter(Query::CREATED_AT, null, $max);
        $query->addFilter($filter);

        $filters = $query->getFilters();
        self::assertSame([$filter], $filters[Query::CREATED_AT], 'Date range filter with max bound only is added.');

        // min and max bound
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $filter = new DateRangeFilter(Query::CREATED_AT, $min, $max);
        $query->addFilter($filter);

        $filters = $query->getFilters();
        self::assertSame([$filter], $filters[Query::CREATED_AT], 'Date range filter with both bound is added.');
    }

    public function testNoOrderByByDefault(): void
    {
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));

        self::assertSame([], $query->getOrderBy(), 'No ORDER BY by default.');
    }

    /**
     * @depends testNoOrderByByDefault
     */
    public function testAddOrderBy(): void
    {
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
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
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));

        $this->expectException(InvalidArgumentException::class);

        $query->addOrderBy(Query::TRANSACTION_HASH, 'unknown');
    }

    public function testNoLimitNoOffsetByDefault(): void
    {
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));

        self::assertSame([0, 0], $query->getLimit(), 'No LIMIT by default.');
    }

    /**
     * @depends testNoLimitNoOffsetByDefault
     */
    public function testLimitWithoutOffset(): void
    {
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $query->limit(10);

        self::assertSame([10, 0], $query->getLimit(), 'LIMIT without offset is OK.');
    }

    /**
     * @depends testNoLimitNoOffsetByDefault
     */
    public function testWithLimitAndOffset(): void
    {
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $query->limit(10, 50);

        self::assertSame([10, 50], $query->getLimit(), 'LIMIT with offset is OK.');
    }

    /**
     * @depends testNoLimitNoOffsetByDefault
     */
    public function testLimitNegativeLimit(): void
    {
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));

        $this->expectException(InvalidArgumentException::class);
        $query->limit(-1, 50);
    }

    /**
     * @depends testNoLimitNoOffsetByDefault
     */
    public function testLimitNegativeOffset(): void
    {
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));

        $this->expectException(InvalidArgumentException::class);
        $query->limit(0, -50);
    }

    /**
     * @depends testAddSimpleFilter
     * @depends testAddOrderBy
     */
    public function testBuildQueryBuilderDefault(): void
    {
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
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
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        // test SQL query with 1 filter
        $expectedQuery = 'SELECT * FROM author_audit at WHERE transaction_hash = :transaction_hash';
        $expectedParameters = [
            'transaction_hash' => '123abc',
        ];
        $query->addFilter(new SimpleFilter(Query::TRANSACTION_HASH, '123abc'));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with 1 filter.');
        self::assertSame($expectedParameters, $queryBuilder->getParameters(), 'Parameters OK with 1 filter.');

        // test SQL query with 2 filters
        $expectedQuery = 'SELECT * FROM author_audit at WHERE transaction_hash IN (:transaction_hash)';
        $expectedParameters = [
            'transaction_hash' => ['123abc', '456def'],
        ];
        $query->addFilter(new SimpleFilter(Query::TRANSACTION_HASH, '456def'));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with 2 filters.');
        self::assertSame($expectedParameters, $queryBuilder->getParameters(), 'Parameters OK with 2 filters.');

        // test SQL query with 3 filters
        $expectedQuery = 'SELECT * FROM author_audit at WHERE transaction_hash IN (:transaction_hash)';
        $expectedParameters = [
            'transaction_hash' => ['123abc', '456def', '789ghj', '012jkl'],
        ];
        $query->addFilter(new SimpleFilter(Query::TRANSACTION_HASH, ['789ghj', '012jkl']));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with 3 filters.');
        self::assertSame($expectedParameters, $queryBuilder->getParameters(), 'Parameters OK with 3 filters.');
    }

    /**
     * @depends testBuildQueryBuilderSimpleFilter
     */
    public function testBuildQueryBuilderExtraIndexFilter(): void
    {
        $query = new Query(
            'author_audit',
            $this->createConnection(),
            $this->createProviderConfiguration([
                'extra_fields' => [
                    'extra_column' => [
                        'type' => 'string',
                        'options' => ['notnull' => true]
                    ]
                ],
                'extra_indices' => ['extra_column' => null]
            ])
        );
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        // test SQL query with 1 filter
        $expectedQuery = 'SELECT * FROM author_audit at WHERE extra_column = :extra_column';
        $expectedParameters = [
            'extra_column' => '123abc',
        ];
        $query->addFilter(new SimpleFilter('extra_column', '123abc'));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with 1 filter.');
        self::assertSame($expectedParameters, $queryBuilder->getParameters(), 'Parameters OK with 1 filter.');

        // test SQL query with 2 filters
        $expectedQuery = 'SELECT * FROM author_audit at WHERE extra_column IN (:extra_column)';
        $expectedParameters = [
            'extra_column' => ['123abc', '456def'],
        ];
        $query->addFilter(new SimpleFilter('extra_column', '456def'));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with 2 filters.');
        self::assertSame($expectedParameters, $queryBuilder->getParameters(), 'Parameters OK with 2 filters.');

        // test SQL query with 3 filters
        $expectedQuery = 'SELECT * FROM author_audit at WHERE extra_column IN (:extra_column)';
        $expectedParameters = [
            'extra_column' => ['123abc', '456def', '789ghj', '012jkl'],
        ];
        $query->addFilter(new SimpleFilter('extra_column', ['789ghj', '012jkl']));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with 3 filters.');
        self::assertSame($expectedParameters, $queryBuilder->getParameters(), 'Parameters OK with 3 filters.');
    }

    /**
     * @depends testBuildQueryBuilderDefault
     */
    public function testBuildQueryBuilderOrderBy(): void
    {
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

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
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

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
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        $expectedQuery = 'SELECT * FROM author_audit at WHERE object_id >= :min_object_id';
        $query->addFilter(new RangeFilter(Query::OBJECT_ID, 5));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with min bound only.');

        // test SQL query with a range filter, max bound only
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        $expectedQuery = 'SELECT * FROM author_audit at WHERE object_id <= :max_object_id';
        $query->addFilter(new RangeFilter(Query::OBJECT_ID, null, 25));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with max bound only.');

        // test SQL query with a range filter with both bounds
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        $expectedQuery = 'SELECT * FROM author_audit at WHERE object_id >= :min_object_id AND object_id <= :max_object_id';
        $query->addFilter(new RangeFilter(Query::OBJECT_ID, 5, 25));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with max bound only.');
    }

    /**
     * @depends testBuildQueryBuilderDefault
     */
    public function testBuildQueryBuilderDateRangeFilter(): void
    {
        $min = new DateTime('-1 day');
        $max = new DateTime('+1 day');

        // test SQL query with a date range filter, min bound only
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        $expectedQuery = 'SELECT * FROM author_audit at WHERE object_id >= :min_object_id';
        $query->addFilter(new RangeFilter(Query::OBJECT_ID, $min->format('Y-m-d H:i:s')));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with min bound only.');

        // test SQL query with a date range filter, max bound only
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        $expectedQuery = 'SELECT * FROM author_audit at WHERE object_id <= :max_object_id';
        $query->addFilter(new RangeFilter(Query::OBJECT_ID, null, $max->format('Y-m-d H:i:s')));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with max bound only.');

        // test SQL query with a date range filter with both bounds
        $query = new Query('author_audit', $this->createConnection(), $this->createProviderConfiguration([]));
        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');

        $expectedQuery = 'SELECT * FROM author_audit at WHERE object_id >= :min_object_id AND object_id <= :max_object_id';
        $query->addFilter(new RangeFilter(Query::OBJECT_ID, $min->format('Y-m-d H:i:s'), $max->format('Y-m-d H:i:s')));
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);
        self::assertSame($expectedQuery, $queryBuilder->getSQL(), 'SQL query is OK with a range filter with max bound only.');
    }
}
