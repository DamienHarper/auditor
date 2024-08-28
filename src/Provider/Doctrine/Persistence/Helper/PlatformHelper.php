<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Helper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use ReflectionClass;

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
            || (isset($columns[$name]['options']['length']) && $columns[$name]['options']['length'] < 191)
        ) {
            return false;
        }

        $version = self::getServerVersion($connection);

        if (null === $version) {
            // Assume index length is not limited
            return false;
        }

        $mariadb = false !== mb_stripos($version, 'mariadb');

        $reflectedClass = new ReflectionClass(AbstractMySQLDriver::class);
        $reflectedMethod = $reflectedClass->getMethod($mariadb ? 'getMariaDbMysqlVersionNumber' : 'getOracleMysqlVersionNumber');
        $reflectedMethod->setAccessible(true);

        /** @var string $normalizedVersion */
        $normalizedVersion = $reflectedMethod->invoke(null, $version);
        $minVersion = $mariadb ? '10.2.2' : '5.7.7';

        return version_compare($normalizedVersion, $minVersion, '<');
    }

    public static function getServerVersion(Connection $connection): ?string
    {
        $nativeConnection = $connection->getNativeConnection();

        if ($nativeConnection instanceof ServerInfoAwareConnection) {
            return $nativeConnection->getServerVersion();
        }

        return null;
    }

    public static function isJsonSupported(Connection $connection): bool
    {
        $version = self::getServerVersion($connection);
        if (null === $version) {
            // Assume JSON is supported
            return true;
        }

        $reflectedClass = new ReflectionClass(AbstractMySQLDriver::class);
        $reflectedMethod = $reflectedClass->getMethod('getMariaDbMysqlVersionNumber');
        $reflectedMethod->setAccessible(true);

        /** @var string $normalizedVersion */
        $normalizedVersion = $reflectedMethod->invoke(null, $version);

        // JSON wasn't supported on MariaDB before 10.2.7
        // @see https://mariadb.com/kb/en/json-data-type/
        return version_compare($normalizedVersion, '10.2.7', '>=');
    }
}
