<?php

namespace DH\Auditor\Provider\Doctrine\Persistence\Helper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;

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
            || $columns[$name]['type'] !== DoctrineHelper::getDoctrineType('STRING')
            || (
                isset($columns[$name]['options'], $columns[$name]['options']['length'])
                && $columns[$name]['options']['length'] < 191
            )
        ) {
            return false;
        }

        $version = self::getServerVersion($connection);

        if (null === $version) {
            return false;
        }

        $mariadb = false !== mb_stripos($version, 'mariadb');
        if ($mariadb && version_compare(self::getMariaDbMysqlVersionNumber($version), '10.2.2', '<')) {
            return true;
        }

        if (!$mariadb && version_compare(self::getOracleMysqlVersionNumber($version), '5.7.7', '<')) {
            return true;
        }

        return false;
    }

    public static function getServerVersion(Connection $connection): ?string
    {
        $wrappedConnection = $connection->getWrappedConnection();

        if ($wrappedConnection instanceof ServerInfoAwareConnection) {
            return $wrappedConnection->getServerVersion();
        }

        return null;
    }

    public static function isJsonSupported(Connection $connection): bool
    {
        $version = self::getServerVersion($connection);
        if (null === $version) {
            return true;
        }

        $mariadb = false !== mb_stripos($version, 'mariadb');
        if ($mariadb && version_compare(self::getMariaDbMysqlVersionNumber($version), '10.2.7', '<')) {
            // JSON wasn't supported on MariaDB before 10.2.7
            // @see https://mariadb.com/kb/en/json-data-type/
            return false;
        }

        // Assume JSON is supported
        return true;
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
            '/^(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+))?)?/',
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
            '/^(?:5\.5\.5-)?(mariadb-)?(?P<major>\d+)\.(?P<minor>\d+)\.(?P<patch>\d+)/i',
            $versionString,
            $versionParts
        );

        return $versionParts['major'].'.'.$versionParts['minor'].'.'.$versionParts['patch'];
    }
}
