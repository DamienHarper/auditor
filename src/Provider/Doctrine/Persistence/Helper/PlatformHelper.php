<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Helper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

abstract class PlatformHelper
{
    /**
     * MySQL < 5.7.7 and MariaDb < 10.2.2 index length requirements.
     *
     * @see https://github.com/doctrine/dbal/issues/3419
     */
    public static function isIndexLengthLimited(string $name, Connection $connection): bool
    {
        $columns = SchemaHelper::getAuditTableColumns();
        if (
            !isset($columns[$name])
            || Types::STRING !== $columns[$name]['type']
            || (isset($columns[$name]['options']['length']) && $columns[$name]['options']['length'] < 191)
        ) {
            return false;
        }

        $version = self::getServerVersion($connection);

        if (null === $version) {
            // Assume no index length limitation
            return false;
        }

        $mariadb = false !== mb_stripos($version, 'mariadb');
        if ($mariadb && version_compare(self::getMariaDbMysqlVersionNumber($version), '10.2.2', '<')) {
            return true;
        }

        return !$mariadb && version_compare(self::getOracleMysqlVersionNumber($version), '5.7.7', '<');
    }

    public static function getServerVersion(Connection $connection): ?string
    {
        if (method_exists($connection, 'getWrappedConnection')) {
            return $connection->getWrappedConnection()->getServerVersion();
        }
        if (method_exists($connection, 'getNativeConnection')) {
            return $connection->getServerVersion();
        }

        return null;
    }

    public static function isJsonSupported(Connection $connection): bool
    {
        $version = self::getServerVersion($connection);

        if (null === $version || false !== mb_stripos($version, 'mariadb')) {
            // Assume JSON is supported
            return true;
        }

        // JSON wasn't supported on MariaDB before 10.2.7
        // @see https://mariadb.com/kb/en/json-data-type/
        return version_compare(self::getMariaDbMysqlVersionNumber($version), '10.2.7', '<');
    }

    /**
     * Get a normalized 'version number' from the server string
     * returned by Oracle MySQL servers.
     *
     * @param string $versionString Version string returned by the driver, i.e. '5.7.10'
     *
     * @copyright Doctrine team
     */
    public static function getOracleMysqlVersionNumber(string $versionString): string
    {
        preg_match(
            '#^(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+))?)?#',
            $versionString,
            $versionParts
        );

        $majorVersion = $versionParts['major'];
        $minorVersion = $versionParts['minor'] ?? 0;
        $patchVersion = $versionParts['patch'] ?? null;

        if ('5' === $majorVersion && '7' === $minorVersion && null === $patchVersion) {
            $patchVersion = '9';
        }

        return $majorVersion.'.'.$minorVersion.'.'.$patchVersion;
    }

    /**
     * Detect MariaDB server version, including hack for some mariadb distributions
     * that starts with the prefix '5.5.5-'.
     *
     * @param string $versionString Version string as returned by mariadb server, i.e. '5.5.5-Mariadb-10.0.8-xenial'
     *
     * @copyright Doctrine team
     */
    public static function getMariaDbMysqlVersionNumber(string $versionString): string
    {
        preg_match(
            '#^(?:5\.5\.5-)?(mariadb-)?(?P<major>\d+)\.(?P<minor>\d+)\.(?P<patch>\d+)#i',
            $versionString,
            $versionParts
        );

        return $versionParts['major'].'.'.$versionParts['minor'].'.'.$versionParts['patch'];
    }
}
