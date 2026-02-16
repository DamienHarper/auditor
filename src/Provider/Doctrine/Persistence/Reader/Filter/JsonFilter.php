<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\JsonPlatformHelper;
use Doctrine\DBAL\Connection;

/**
 * Filter for querying JSON column content.
 *
 * Supports extraction of scalar values from JSON columns with platform-specific SQL generation.
 * When the database doesn't support JSON functions, falls back to LIKE pattern matching
 * (unless strict mode is enabled).
 *
 * Supported operators: =, !=, <>, LIKE, NOT LIKE, IN, NOT IN, IS NULL, IS NOT NULL
 *
 * @example
 * // Filter by exact value
 * new JsonFilter('extra_data', 'department', 'IT')
 *
 * // Filter with LIKE
 * new JsonFilter('extra_data', 'department', 'IT%', 'LIKE')
 *
 * // Filter with IN
 * new JsonFilter('extra_data', 'status', ['active', 'pending'], 'IN')
 *
 * // Strict mode - throws exception if JSON not supported
 * new JsonFilter('extra_data', 'department', 'IT', '=', true)
 *
 * @note Only scalar value extraction is supported in this version.
 *       Nested arrays/objects comparison (e.g., JSON_CONTAINS) is not yet implemented.
 */
final readonly class JsonFilter implements PlatformAwareFilterInterface
{
    private const array VALID_OPERATORS = ['=', '!=', '<>', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL'];

    private const array NULL_OPERATORS = ['IS NULL', 'IS NOT NULL'];

    /**
     * @param string $column   The JSON column name (e.g., 'extra_data')
     * @param string $path     The JSON path to extract (e.g., 'department' or 'user.role')
     * @param mixed  $value    The value to compare against (ignored for IS NULL/IS NOT NULL)
     * @param string $operator The comparison operator
     * @param bool   $strict   If true, throws exception when JSON is not supported instead of falling back to LIKE
     *
     * @throws InvalidArgumentException If operator is not valid or if value is invalid for the operator
     */
    public function __construct(
        private string $column,
        private string $path,
        private mixed $value = null,
        private string $operator = '=',
        private bool $strict = false,
    ) {
        $normalizedOperator = mb_strtoupper(mb_trim($this->operator));

        if (!\in_array($normalizedOperator, self::VALID_OPERATORS, true)) {
            throw new InvalidArgumentException(\sprintf(
                'Invalid operator "%s". Allowed operators: %s',
                $this->operator,
                implode(', ', self::VALID_OPERATORS)
            ));
        }

        if (\in_array($normalizedOperator, ['IN', 'NOT IN'], true) && !\is_array($this->value)) {
            throw new InvalidArgumentException(\sprintf(
                'Operator "%s" requires an array value.',
                $this->operator
            ));
        }

        if (!\in_array($normalizedOperator, self::NULL_OPERATORS, true) && !\in_array($normalizedOperator, ['IN', 'NOT IN'], true) && \is_array($this->value)) {
            throw new InvalidArgumentException(\sprintf(
                'Operator "%s" does not accept array values. Use IN or NOT IN instead.',
                $this->operator
            ));
        }
    }

    public function getName(): string
    {
        return 'json';
    }

    /**
     * @throws InvalidArgumentException Always throws as this filter requires a connection
     */
    #[\Deprecated(message: 'Use getSQLWithConnection() instead')]
    public function getSQL(): array
    {
        throw new InvalidArgumentException(
            'JsonFilter requires a database connection. Use getSQLWithConnection() instead.'
        );
    }

    public function getSQLWithConnection(Connection $connection): array
    {
        $normalizedOperator = mb_strtoupper(mb_trim($this->operator));

        // Check JSON support
        if (!JsonPlatformHelper::isJsonSearchSupported($connection)) {
            return $this->handleUnsupportedJson($connection, $normalizedOperator);
        }

        return $this->buildJsonSql($connection, $normalizedOperator);
    }

    /**
     * Build SQL using native JSON functions.
     *
     * @return array{sql: string, params: array<string, mixed>}
     */
    private function buildJsonSql(Connection $connection, string $operator): array
    {
        $jsonExtract = JsonPlatformHelper::buildJsonExtractSql($connection, $this->column, $this->path);
        $paramName = 'json_'.preg_replace('/[^a-zA-Z0-9_]/', '_', $this->path);

        // Handle NULL operators
        if ('IS NULL' === $operator) {
            return [
                'sql' => \sprintf('(%s IS NULL OR %s IS NULL)', $this->column, $jsonExtract),
                'params' => [],
            ];
        }

        if ('IS NOT NULL' === $operator) {
            return [
                'sql' => \sprintf('(%s IS NOT NULL AND %s IS NOT NULL)', $this->column, $jsonExtract),
                'params' => [],
            ];
        }

        // Handle IN/NOT IN operators
        if (\in_array($operator, ['IN', 'NOT IN'], true)) {
            return [
                'sql' => \sprintf('%s %s (:%s)', $jsonExtract, $operator, $paramName),
                'params' => [$paramName => $this->value],
            ];
        }

        // Handle standard operators
        return [
            'sql' => \sprintf('%s %s :%s', $jsonExtract, $operator, $paramName),
            'params' => [$paramName => $this->value],
        ];
    }

    /**
     * Handle case when JSON is not supported by the database.
     *
     * @return array{sql: string, params: array<string, mixed>}
     *
     * @throws InvalidArgumentException If strict mode is enabled
     */
    private function handleUnsupportedJson(Connection $connection, string $operator): array
    {
        $platformName = JsonPlatformHelper::getPlatformName($connection);
        $minVersions = JsonPlatformHelper::getMinimumVersions();

        $message = \sprintf(
            'JSON search is not supported on this %s version. Minimum required versions: MySQL %s, MariaDB %s, PostgreSQL %s, SQLite %s.',
            $platformName,
            $minVersions['mysql'],
            $minVersions['mariadb'],
            $minVersions['postgresql'],
            $minVersions['sqlite']
        );

        if ($this->strict) {
            throw new InvalidArgumentException($message.' Strict mode is enabled, fallback to LIKE is disabled.');
        }

        // Trigger warning
        trigger_error(
            $message.' Falling back to LIKE pattern matching which may produce inaccurate results.',
            E_USER_WARNING
        );

        return $this->buildFallbackLikeSql($operator);
    }

    /**
     * Build fallback SQL using LIKE pattern matching.
     *
     * @return array{sql: string, params: array<string, mixed>}
     */
    private function buildFallbackLikeSql(string $operator): array
    {
        $paramName = 'json_like_'.preg_replace('/[^a-zA-Z0-9_]/', '_', $this->path);

        // Handle NULL operators
        if ('IS NULL' === $operator) {
            // Check if column is NULL or doesn't contain the path
            return [
                'sql' => \sprintf("(%s IS NULL OR %s NOT LIKE '%%\"%s\":%%')", $this->column, $this->column, $this->path),
                'params' => [],
            ];
        }

        if ('IS NOT NULL' === $operator) {
            return [
                'sql' => \sprintf("(%s IS NOT NULL AND %s LIKE '%%\"%s\":%%')", $this->column, $this->column, $this->path),
                'params' => [],
            ];
        }

        // Handle IN operator - combine multiple LIKE patterns with OR
        if ('IN' === $operator) {
            $conditions = [];
            $params = [];
            foreach ($this->value as $i => $val) {
                $pName = $paramName.'_'.$i;
                $conditions[] = \sprintf('%s LIKE :%s', $this->column, $pName);
                $params[$pName] = JsonPlatformHelper::buildFallbackLikePattern($this->path, $val);
            }

            return [
                'sql' => '('.implode(' OR ', $conditions).')',
                'params' => $params,
            ];
        }

        // Handle NOT IN operator
        if ('NOT IN' === $operator) {
            $conditions = [];
            $params = [];
            foreach ($this->value as $i => $val) {
                $pName = $paramName.'_'.$i;
                $conditions[] = \sprintf('%s NOT LIKE :%s', $this->column, $pName);
                $params[$pName] = JsonPlatformHelper::buildFallbackLikePattern($this->path, $val);
            }

            return [
                'sql' => '('.implode(' AND ', $conditions).')',
                'params' => $params,
            ];
        }

        // Handle != and <> operators
        if (\in_array($operator, ['!=', '<>'], true)) {
            $pattern = JsonPlatformHelper::buildFallbackLikePattern($this->path, $this->value);

            return [
                'sql' => \sprintf('%s NOT LIKE :%s', $this->column, $paramName),
                'params' => [$paramName => $pattern],
            ];
        }

        // Handle LIKE operator - user provides their own pattern
        if ('LIKE' === $operator) {
            // For LIKE, we search the raw value in the column
            return [
                'sql' => \sprintf('%s LIKE :%s', $this->column, $paramName),
                'params' => [$paramName => '%'.$this->value.'%'],
            ];
        }

        // Handle NOT LIKE operator
        if ('NOT LIKE' === $operator) {
            return [
                'sql' => \sprintf('%s NOT LIKE :%s', $this->column, $paramName),
                'params' => [$paramName => '%'.$this->value.'%'],
            ];
        }

        // Default: = operator
        $pattern = JsonPlatformHelper::buildFallbackLikePattern($this->path, $this->value);

        return [
            'sql' => \sprintf('%s LIKE :%s', $this->column, $paramName),
            'params' => [$paramName => $pattern],
        ];
    }
}
