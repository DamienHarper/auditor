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
//dump(__METHOD__, $name);
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
//dump(__METHOD__);
        $params = self::getConnectionParameters($params);

        if ('pdo_sqlite' === $params['driver']) {
            // SQLite
//dump('SQLite');
//dump(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2));
//dump($params);
            $connection = DriverManager::getConnection($params);
            $sm = $connection->getSchemaManager();
            $schema = $sm->createSchema();
            $stmts = $schema->toDropSql($connection->getDatabasePlatform());
            foreach ($stmts as $stmt) {
                $connection->exec($stmt);
            }
        } else {
            // Other
//dump('Other DB');
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
        if (isset(
            $GLOBALS['db_type'],
            $GLOBALS['db_username'],
            $GLOBALS['db_password'],
            $GLOBALS['db_host'],
            $GLOBALS['db_name'],
            $GLOBALS['db_port']
        )) {
            $params = [
                'driver' => $GLOBALS['db_type'],
                'user' => $GLOBALS['db_username'],
                'password' => $GLOBALS['db_password'],
                'host' => $GLOBALS['db_host'],
                'dbname' => $GLOBALS['db_name'],
                'port' => $GLOBALS['db_port'],
            ];
        } elseif (null !== $params) {
            // do nothing
        } else {
            // in memory SQLite DB
            $params = [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ];
        }

        return $params;
    }
}
