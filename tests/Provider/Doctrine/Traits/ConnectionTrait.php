<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

trait ConnectionTrait
{
    /**
     * @var Connection[]
     */
    private static $connections = [];

    private function getConnection(string $name = 'default', ?array $params = null): Connection
    {
        if (!isset(self::$connections[$name]) || null === self::$connections[$name]) {
            self::$connections[$name] = $this->createConnection($params);
        }

        if (false === self::$connections[$name]->ping()) {
            self::$connections[$name]->close();
            self::$connections[$name]->connect();
        }

        return self::$connections[$name];
    }

    private function createConnection(?array $params = null): Connection
    {
        $params = self::getConnectionParameters($params);

        if ('pdo_sqlite' === $params['driver']) {
            // SQLite
            $connection = DriverManager::getConnection($params);
            $sm = $connection->getSchemaManager();
            $schema = $sm->createSchema();
            $stmts = $schema->toDropSql($connection->getDatabasePlatform());
            foreach ($stmts as $stmt) {
                $connection->exec($stmt);
            }
        } else {
            // Other
            $tmpParams = $params;
            $dbname = $params['dbname'];
            unset($tmpParams['dbname']);

            $connection = DriverManager::getConnection($tmpParams);

            if ($connection->getDatabasePlatform()->supportsCreateDropDatabase()) {
                $connection->getSchemaManager()->dropAndCreateDatabase($dbname);
            } else {
                $sm = $connection->getSchemaManager();
                $schema = $sm->createSchema();
                $stmts = $schema->toDropSql($connection->getDatabasePlatform());
                foreach ($stmts as $stmt) {
                    $connection->exec($stmt);
                }
            }
        }

        $connection->close();

        return DriverManager::getConnection($params);
    }

    private static function getConnectionParameters(?array $params = null): array
    {
        if (null === $params && !isset(
            $GLOBALS['db_type'],
            $GLOBALS['db_username'],
            $GLOBALS['db_password'],
            $GLOBALS['db_host'],
            $GLOBALS['db_name'],
            $GLOBALS['db_port']
        )) {
            // in memory SQLite DB
            return [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ];
        }

        if (null !== $params) {
            // provided params take precedence
            return $params;
        }

        // fallback to what's defined in $GLOBALS (from phpunit config file)
        return [
            'driver' => $GLOBALS['db_type'],
            'user' => $GLOBALS['db_username'],
            'password' => $GLOBALS['db_password'],
            'host' => $GLOBALS['db_host'],
            'dbname' => $GLOBALS['db_name'],
            'port' => $GLOBALS['db_port'],
        ];
    }
}
