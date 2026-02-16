<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Persistence\Reader\Filter;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\JsonFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ConnectionTrait;
use DH\Auditor\Tests\Traits\ReflectionTrait;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class JsonFilterTest extends TestCase
{
    use ConnectionTrait;
    use ReflectionTrait;

    public function testGetName(): void
    {
        $filter = new JsonFilter('extra_data', 'department', 'IT');

        $this->assertSame('json', $filter->getName());
    }

    public function testInvalidOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid operator');

        new JsonFilter('extra_data', 'department', 'IT', 'INVALID');
    }

    public function testInOperatorRequiresArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires an array value');

        new JsonFilter('extra_data', 'department', 'IT', 'IN');
    }

    public function testNotInOperatorRequiresArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires an array value');

        new JsonFilter('extra_data', 'department', 'IT', 'NOT IN');
    }

    public function testEqualsOperatorRejectsArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not accept array values');

        new JsonFilter('extra_data', 'department', ['IT', 'HR'], '=');
    }

    #[IgnoreDeprecations]
    public function testGetSQLThrowsException(): void
    {
        $filter = new JsonFilter('extra_data', 'department', 'IT');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a database connection');

        $filter->getSQL();
    }

    public function testGetSQLWithConnectionEquals(): void
    {
        $connection = $this->createConnection();
        $filter = new JsonFilter('extra_data', 'department', 'IT');

        $result = $filter->getSQLWithConnection($connection);

        $this->assertArrayHasKey('sql', $result);
        $this->assertArrayHasKey('params', $result);
        $this->assertArrayHasKey('json_department', $result['params']);
        $this->assertSame('IT', $result['params']['json_department']);
    }

    public function testGetSQLWithConnectionNotEquals(): void
    {
        $connection = $this->createConnection();
        $filter = new JsonFilter('extra_data', 'department', 'IT', '!=');

        $result = $filter->getSQLWithConnection($connection);

        $this->assertArrayHasKey('sql', $result);
        $this->assertStringContainsString('!=', $result['sql']);
    }

    public function testGetSQLWithConnectionLike(): void
    {
        $connection = $this->createConnection();
        $filter = new JsonFilter('extra_data', 'department', 'IT%', 'LIKE');

        $result = $filter->getSQLWithConnection($connection);

        $this->assertArrayHasKey('sql', $result);
        $this->assertStringContainsString('LIKE', $result['sql']);
    }

    public function testGetSQLWithConnectionIn(): void
    {
        $connection = $this->createConnection();
        $filter = new JsonFilter('extra_data', 'department', ['IT', 'HR', 'Finance'], 'IN');

        $result = $filter->getSQLWithConnection($connection);

        $this->assertArrayHasKey('sql', $result);
        $this->assertStringContainsString('IN', $result['sql']);
        $this->assertSame(['IT', 'HR', 'Finance'], $result['params']['json_department']);
    }

    public function testGetSQLWithConnectionNotIn(): void
    {
        $connection = $this->createConnection();
        $filter = new JsonFilter('extra_data', 'department', ['IT', 'HR'], 'NOT IN');

        $result = $filter->getSQLWithConnection($connection);

        $this->assertArrayHasKey('sql', $result);
        $this->assertStringContainsString('NOT IN', $result['sql']);
    }

    public function testGetSQLWithConnectionIsNull(): void
    {
        $connection = $this->createConnection();
        $filter = new JsonFilter('extra_data', 'department', null, 'IS NULL');

        $result = $filter->getSQLWithConnection($connection);

        $this->assertArrayHasKey('sql', $result);
        $this->assertStringContainsString('IS NULL', $result['sql']);
        $this->assertSame([], $result['params']);
    }

    public function testGetSQLWithConnectionIsNotNull(): void
    {
        $connection = $this->createConnection();
        $filter = new JsonFilter('extra_data', 'department', null, 'IS NOT NULL');

        $result = $filter->getSQLWithConnection($connection);

        $this->assertArrayHasKey('sql', $result);
        $this->assertStringContainsString('IS NOT NULL', $result['sql']);
        $this->assertSame([], $result['params']);
    }

    public function testNestedJsonPath(): void
    {
        $connection = $this->createConnection();
        $filter = new JsonFilter('extra_data', 'user.role', 'admin');

        $result = $filter->getSQLWithConnection($connection);

        $this->assertArrayHasKey('sql', $result);
        $this->assertArrayHasKey('params', $result);
        // The param name should be sanitized (dots replaced with underscores)
        $this->assertArrayHasKey('json_user_role', $result['params']);
    }

    #[Depends('testGetSQLWithConnectionEquals')]
    public function testQueryIntegrationWithJsonFilter(): void
    {
        $connection = $this->createConnection();
        $query = new Query('test_audit', $connection, 'UTC');
        $filter = new JsonFilter('extra_data', 'department', 'IT');

        $query->addFilter($filter);

        $filters = $query->getFilters();
        $this->assertCount(1, $filters[Query::JSON]);
        $this->assertSame($filter, $filters[Query::JSON][0]);
    }

    #[Depends('testQueryIntegrationWithJsonFilter')]
    public function testBuildQueryBuilderWithJsonFilter(): void
    {
        $connection = $this->createConnection();
        $query = new Query('test_audit', $connection, 'UTC');
        $filter = new JsonFilter('extra_data', 'department', 'IT');

        $query->addFilter($filter);

        $reflectedMethod = $this->reflectMethod($query, 'buildQueryBuilder');
        $queryBuilder = $reflectedMethod->invokeArgs($query, []);

        $sql = $queryBuilder->getSQL();
        $params = $queryBuilder->getParameters();

        // Should contain the JSON extraction and comparison
        $this->assertStringContainsString('extra_data', (string) $sql);
        $this->assertStringContainsString('department', (string) $sql);
        $this->assertArrayHasKey('json_department', $params);
        $this->assertSame('IT', $params['json_department']);
    }
}
