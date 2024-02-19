<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Event;

use DH\Auditor\Provider\Doctrine\Auditing\Event\DoctrineSubscriber;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\LoggerChain;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\DHDriver;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as ConnectionDbal;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\MySQL\ExceptionConverter;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
final class DoctrineSubscriberTest extends TestCase
{
    public function testIssue185(): void
    {
        $transactionManager = $this->createMock(TransactionManager::class);
        $objectManager = $this->createMock(EntityManagerInterface::class);

        $args = new OnFlushEventArgs($objectManager);

        $objectManager
            ->method('getConnection')
            ->willReturn($connection = $this->createMock(ConnectionDbal::class))
        ;

        $connection
            ->method('getDriver')
            ->willReturn($driver = $this->createMock(Driver::class))
        ;

        $connection
            ->method('getConfiguration')
            ->willReturn($configuration = new Configuration())
        ;

        $configuration->setSQLLogger(new class() implements SQLLogger {
            public function startQuery($sql, ?array $params = null, ?array $types = null): void {}

            public function stopQuery(): void {}
        });

        $target = new DoctrineSubscriber($transactionManager);
        $target->onFlush($args);
        $target->onFlush($args);
        $target->onFlush($args);
        $target->onFlush($args);
        $target->onFlush($args);

        $result = $configuration->getSQLLogger();
        self::assertCount(2, $result->getLoggers());
    }

    public function testIssue184IfAbstractDriverMiddleware(): void
    {
        $transactionManager = $this->createMock(TransactionManager::class);
        $objectManager = $this->createMock(EntityManagerInterface::class);

        $args = new OnFlushEventArgs($objectManager);

        $nativeDriver = $this->createMock(Driver::class);
        $dhDriver = new DHDriver($nativeDriver);
        $driver = new class($dhDriver) extends AbstractDriverMiddleware {};

        $objectManager
            ->method('getConnection')
            ->willReturn($connection = $this->createMock(ConnectionDbal::class))
        ;

        $connection
            ->method('getDriver')
            ->willReturn($driver)
        ;

        $target = new DoctrineSubscriber($transactionManager);
        $target->onFlush($args);

        $transactionManager
            ->expects(self::once())
            ->method('process')
        ;

        foreach ($dhDriver->getFlusherList() as $item) {
            ($item)();
        }
    }

    public function testIssue184IfNotAbstractDriverMiddleware(): void
    {
        $transactionManager = $this->createMock(TransactionManager::class);
        $objectManager = $this->createMock(EntityManagerInterface::class);

        $args = new OnFlushEventArgs($objectManager);

        $nativeDriver = $this->createMock(Driver::class);
        $dhDriver = new DHDriver($nativeDriver);
        $driver = new class($dhDriver) implements VersionAwarePlatformDriver {
            /** @noinspection PhpPropertyOnlyWrittenInspection */
            private Driver $wrappedDriver;

            public function __construct(Driver $wrappedDriver)
            {
                $this->wrappedDriver = $wrappedDriver;
            }

            public function connect(array $params): void {}

            public function getDatabasePlatform(): void {}

            public function getSchemaManager(ConnectionDbal $conn, AbstractPlatform $platform): void {}

            public function getExceptionConverter(): Driver\API\ExceptionConverter
            {
                return new ExceptionConverter();
            }

            public function createDatabasePlatformForVersion($version): void {}
        };

        $objectManager
            ->method('getConnection')
            ->willReturn($connection = $this->createMock(ConnectionDbal::class))
        ;

        $connection
            ->method('getDriver')
            ->willReturn($driver)
        ;

        $target = new DoctrineSubscriber($transactionManager);
        $target->onFlush($args);

        $transactionManager
            ->expects(self::once())
            ->method('process')
        ;

        foreach ($dhDriver->getFlusherList() as $item) {
            ($item)();
        }
    }

    public function testIssue184Unexpected(): void
    {
        $transactionManager = $this->createMock(TransactionManager::class);
        $objectManager = $this->createMock(EntityManagerInterface::class);

        $args = new OnFlushEventArgs($objectManager);

        $driver = new class() implements VersionAwarePlatformDriver {
            public function connect(array $params): void {}

            public function getDatabasePlatform(): void {}

            public function getSchemaManager(ConnectionDbal $conn, AbstractPlatform $platform): void {}

            public function getExceptionConverter(): Driver\API\ExceptionConverter
            {
                return new ExceptionConverter();
            }

            public function createDatabasePlatformForVersion($version): void {}
        };

        $objectManager
            ->method('getConnection')
            ->willReturn($connection = $this->createMock(ConnectionDbal::class))
        ;

        $connection
            ->method('getDriver')
            ->willReturn($driver)
        ;

        $connection
            ->method('getConfiguration')
            ->willReturn($configuration = $this->createMock(Configuration::class))
        ;

        $transactionManager
            ->expects(self::never())
            ->method('process')
        ;

        $configuration->expects(self::once())
            ->method('setSQLLogger')
            ->with(self::isInstanceOf(LoggerChain::class))
        ;

        $target = new DoctrineSubscriber($transactionManager);
        $target->onFlush($args);
    }
}
