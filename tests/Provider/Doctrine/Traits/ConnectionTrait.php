<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Traits;

use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\DHMiddleware;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Throwable;

trait ConnectionTrait
{
    /**
     * @var Connection[]
     */
    private static array $connections = [];

    private function getConnection(string $name = 'default', ?array $params = null): Connection
    {
        if (!isset(self::$connections[$name]) || null === self::$connections[$name]) {
            self::$connections[$name] = $this->createConnection($params);
        }

        return self::$connections[$name];
    }

    private function createConnection(?array $params = null): Connection
    {
        $params = self::getConnectionParameters($params);

        $config = new Configuration();
        $config->setMiddlewares([
            new DHMiddleware(),
        ]);
        if ('pdo_sqlite' === $params['driver']) {
            // SQLite
            $connection = DriverManager::getConnection($params, $config);
            $schemaManager = DoctrineHelper::createSchemaManager($connection);
            $schema = DoctrineHelper::introspectSchema($schemaManager);
            $stmts = $schema->toDropSql($connection->getDatabasePlatform());
            foreach ($stmts as $stmt) {
                $connection->executeStatement($stmt);
            }
        } else {
            $tmpParams = $params;
            $dbname = $params['dbname'];
            unset($tmpParams['dbname']);

            $connection = DriverManager::getConnection($tmpParams, $config);

            if ('pdo_pgsql' === $params['driver']) {
                // Closes active connections
                $connection->executeStatement(
                    'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '.$connection->getDatabasePlatform()->quoteStringLiteral($dbname)
                );
            }

            $this->dropAndCreateDatabase($connection->createSchemaManager(), $dbname);
        }

        return DriverManager::getConnection($params, $config);
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
            'charset' => $GLOBALS['db_charset'],
        ];
    }

    private function dropAndCreateDatabase(AbstractSchemaManager $schemaManager, string $dbname): void
    {
        try {
            $schemaManager->dropDatabase($dbname);
        } catch (Throwable) {
            // do nothing
        }

        $schemaManager->createDatabase($dbname);
    }
}
