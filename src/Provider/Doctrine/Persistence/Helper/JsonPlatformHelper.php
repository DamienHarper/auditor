<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Helper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;

/**
 * Helper class for JSON-related SQL generation across different database platforms.
 *
 * Supports:
 * - MySQL 5.7+
 * - MariaDB 10.2.3+
 * - PostgreSQL 9.4+
 * - SQLite 3.38+
 */
abstract class JsonPlatformHelper
{
    /**
     * Minimum versions required for JSON search support.
     */
    private const array MINIMUM_VERSIONS = [
        'mysql' => '5.7.0',
        'mariadb' => '10.2.3',
        'postgresql' => '9.4.0',
        'sqlite' => '3.38.0',
    ];

    /**
     * Check if the database platform supports JSON search functions.
     */
    public static function isJsonSearchSupported(Connection $connection): bool
    {
        $platform = $connection->getDatabasePlatform();
        $version = PlatformHelper::getServerVersion($connection);

        if (null === $version) {
            // Cannot determine version, assume supported for modern DBAL
            return true;
        }

        if ($platform instanceof MariaDBPlatform) {
            return version_compare(
                PlatformHelper::getMariaDbMysqlVersionNumber($version),
                self::MINIMUM_VERSIONS['mariadb'],
                '>='
            );
        }

        if ($platform instanceof MySQLPlatform) {
            return version_compare(
                PlatformHelper::getOracleMysqlVersionNumber($version),
                self::MINIMUM_VERSIONS['mysql'],
                '>='
            );
        }

        if ($platform instanceof PostgreSQLPlatform) {
            return version_compare(
                self::getPostgreSQLVersionNumber($version),
                self::MINIMUM_VERSIONS['postgresql'],
                '>='
            );
        }

        if ($platform instanceof SQLitePlatform) {
            return version_compare(
                self::getSQLiteVersionNumber($version),
                self::MINIMUM_VERSIONS['sqlite'],
                '>='
            );
        }

        // Unknown platform, assume not supported
        return false;
    }

    /**
     * Build SQL expression to extract a JSON value at the given path.
     *
     * @param string $column The JSON column name (e.g., 'extra_data')
     * @param string $path   The JSON path without $ prefix (e.g., 'department' or 'user.name')
     *
     * @return string SQL expression that extracts the value as text
     */
    public static function buildJsonExtractSql(Connection $connection, string $column, string $path): string
    {
        $platform = $connection->getDatabasePlatform();
        $jsonPath = '$.'.$path;

        if ($platform instanceof MariaDBPlatform) {
            // MariaDB: JSON_UNQUOTE(JSON_EXTRACT(column, '$.path'))
            return \sprintf("JSON_UNQUOTE(JSON_EXTRACT(%s, '%s'))", $column, $jsonPath);
        }

        if ($platform instanceof MySQLPlatform) {
            // MySQL 5.7+: JSON_UNQUOTE(JSON_EXTRACT(column, '$.path'))
            // MySQL 8.0+: column->>'$.path' is equivalent but we use the function for compatibility
            return \sprintf("JSON_UNQUOTE(JSON_EXTRACT(%s, '%s'))", $column, $jsonPath);
        }

        if ($platform instanceof PostgreSQLPlatform) {
            // PostgreSQL: column->>'path' for simple paths, or column #>> '{path,subpath}' for nested
            if (!str_contains($path, '.')) {
                return \sprintf("%s->>'%s'", $column, $path);
            }

            // Nested path: convert 'user.name' to '{user,name}'
            $pathParts = explode('.', $path);
            $pgPath = '{'.implode(',', $pathParts).'}';

            return \sprintf("%s #>> '%s'", $column, $pgPath);
        }

        if ($platform instanceof SQLitePlatform) {
            // SQLite 3.38+: json_extract(column, '$.path')
            // Note: SQLite returns unquoted strings by default for json_extract
            return \sprintf("json_extract(%s, '%s')", $column, $jsonPath);
        }

        // Fallback for unknown platforms - will likely fail but provides diagnostic info
        return \sprintf("JSON_EXTRACT(%s, '%s')", $column, $jsonPath);
    }

    /**
     * Build a fallback LIKE pattern for searching JSON content.
     *
     * @param string $path  The JSON path (e.g., 'department')
     * @param mixed  $value The value to search for
     *
     * @return string LIKE pattern
     */
    public static function buildFallbackLikePattern(string $path, mixed $value): string
    {
        // Build pattern like: %"department":"value"% or %"department":value%
        if (\is_string($value)) {
            return \sprintf('%%"%s":"%s"%%', $path, $value);
        }

        if (\is_bool($value)) {
            return \sprintf('%%"%s":%s%%', $path, $value ? 'true' : 'false');
        }

        if (null === $value) {
            return \sprintf('%%"%s":null%%', $path);
        }

        // Numeric value
        return \sprintf('%%"%s":%s%%', $path, (string) $value);
    }

    /**
     * Get the minimum required versions for JSON search support.
     *
     * @return array<string, string>
     */
    public static function getMinimumVersions(): array
    {
        return self::MINIMUM_VERSIONS;
    }

    /**
     * Get the platform name for error messages.
     */
    public static function getPlatformName(Connection $connection): string
    {
        $platform = $connection->getDatabasePlatform();

        return match (true) {
            $platform instanceof MariaDBPlatform => 'MariaDB',
            $platform instanceof MySQLPlatform => 'MySQL',
            $platform instanceof PostgreSQLPlatform => 'PostgreSQL',
            $platform instanceof SQLitePlatform => 'SQLite',
            default => $platform::class,
        };
    }

    /**
     * Extract PostgreSQL version number.
     */
    private static function getPostgreSQLVersionNumber(string $versionString): string
    {
        // PostgreSQL version strings are typically like "15.2" or "PostgreSQL 15.2 on ..."
        if (preg_match('#(\d+)\.(\d+)(?:\.(\d+))?#', $versionString, $matches)) {
            return $matches[1].'.'.$matches[2].'.'.($matches[3] ?? '0');
        }

        return '0.0.0';
    }

    /**
     * Extract SQLite version number.
     */
    private static function getSQLiteVersionNumber(string $versionString): string
    {
        // SQLite version strings are typically like "3.38.5"
        if (preg_match('#(\d+)\.(\d+)\.(\d+)#', $versionString, $matches)) {
            return $matches[1].'.'.$matches[2].'.'.$matches[3];
        }

        return '0.0.0';
    }
}
