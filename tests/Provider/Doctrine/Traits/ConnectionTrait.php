<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

trait ConnectionTrait
{
    /**
     * @var Connection
     */
    private static $connection;

    private function getConnection(): Connection
    {
        if (null === self::$connection) {
            self::$connection = $this->createConnection();
        }

        if (false === self::$connection->ping()) {
            self::$connection->close();
            self::$connection->connect();
        }

        return self::$connection;
    }

    private function createConnection(): Connection
    {
        $params = self::getConnectionParameters();

        if (isset(
            $GLOBALS['db_type'],
            $GLOBALS['db_username'],
            $GLOBALS['db_password'],
            $GLOBALS['db_host'],
            $GLOBALS['db_name'],
            $GLOBALS['db_port']
        )) {
            $tmpParams = $params;
            $dbname = $params['dbname'];
            unset($tmpParams['dbname']);

            $conn = DriverManager::getConnection($tmpParams);
            $platform = $conn->getDatabasePlatform();

            if ($platform->supportsCreateDropDatabase()) {
                $conn->getSchemaManager()->dropAndCreateDatabase($dbname);
            } else {
                $sm = $conn->getSchemaManager();
                $schema = $sm->createSchema();
                $stmts = $schema->toDropSql($conn->getDatabasePlatform());
                foreach ($stmts as $stmt) {
                    $conn->exec($stmt);
                }
            }

            $conn->close();
        }

        return DriverManager::getConnection($params);
    }

    private static function getConnectionParameters(): array
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
        } else {
            $params = [
                'driver' => 'pdo_sqlite',
                'memory' => true,
//                'path' => __DIR__.'/../../dams.sqlite',
            ];
        }

        return $params;
    }
}
