<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Reader\Filter;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\NullFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ConnectionTrait;
use DH\Auditor\Tests\Traits\ReflectionTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class NullFilterTest extends TestCase
{
    use ConnectionTrait;
    use ReflectionTrait;

    public function testGetNameReturnsConstructorArgument(): void
    {
        $filter = new NullFilter('blame_id');

        $this->assertSame('blame_id', $filter->getName());
    }

    public function testGetNameWorksForAllSupportedFields(): void
    {
        $fields = [Query::USER_ID, Query::OBJECT_ID, Query::TYPE, Query::TRANSACTION_HASH, Query::ID];

        foreach ($fields as $field) {
            $filter = new NullFilter($field);
            $this->assertSame($field, $filter->getName(), \sprintf('getName() should return "%s".', $field));
        }
    }

    public function testGetSQLReturnsIsNullExpression(): void
    {
        $filter = new NullFilter('blame_id');

        $result = $filter->getSQL();

        $this->assertArrayHasKey('sql', $result);
        $this->assertArrayHasKey('params', $result);
        $this->assertSame('blame_id IS NULL', $result['sql']);
    }

    public function testGetSQLProducesNoParams(): void
    {
        $filter = new NullFilter('blame_id');

        $result = $filter->getSQL();

        $this->assertSame([], $result['params'], 'NullFilter must not produce bound parameters.');
    }

    public function testGetSQLFieldNameIsEmbeddedInExpression(): void
    {
        foreach (['blame_id', 'object_id', 'type'] as $field) {
            $filter = new NullFilter($field);
            $sql = $filter->getSQL()['sql'];

            $this->assertStringStartsWith($field, $sql);
            $this->assertStringContainsString('IS NULL', (string) $sql);
        }
    }

    public function testQueryAcceptsNullFilterForUserIdField(): void
    {
        $connection = $this->createConnection();
        $query = new Query('test_audit', $connection, 'UTC');
        $filter = new NullFilter(Query::USER_ID);

        $query->addFilter($filter);

        $filters = $query->getFilters();
        $this->assertCount(1, $filters[Query::USER_ID]);
        $this->assertSame($filter, $filters[Query::USER_ID][0]);
    }

    public function testQueryRejectsNullFilterForUnknownField(): void
    {
        $connection = $this->createConnection();
        $query = new Query('test_audit', $connection, 'UTC');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported "unknown_field" filter');

        $query->addFilter(new NullFilter('unknown_field'));
    }

    public function testQueryBuilderIncludesIsNullClause(): void
    {
        $connection = $this->createConnection();
        $query = new Query('test_audit', $connection, 'UTC');
        $query->addFilter(new NullFilter(Query::USER_ID));

        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);

        $sql = $queryBuilder->getSQL();
        $this->assertStringContainsString('IS NULL', (string) $sql);
        $this->assertStringContainsString('blame_id', (string) $sql);
    }

    public function testQueryBuilderIsNullProducesNoParameters(): void
    {
        $connection = $this->createConnection();
        $query = new Query('test_audit', $connection, 'UTC');
        $query->addFilter(new NullFilter(Query::USER_ID));

        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);

        $this->assertSame([], $queryBuilder->getParameters());
    }
}
