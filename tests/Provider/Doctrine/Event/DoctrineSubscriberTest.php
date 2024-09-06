<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Event;

use DH\Auditor\Provider\Doctrine\Auditing\DBAL\Middleware\AuditorDriver;
use DH\Auditor\Provider\Doctrine\Auditing\Event\DoctrineSubscriber;
use DH\Auditor\Transaction\TransactionManagerInterface;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as ConnectionDbal;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\MySQL\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class DoctrineSubscriberTest extends TestCase
{
    public function testIssue184IfAbstractDriverMiddleware(): void
    {
        $transactionManager = new class implements TransactionManagerInterface {
            public function populate($transaction): void {}

            public function process($transaction): void
            {
                static $i = 0;
                ++$i;
                if ($i > 1) {
                    throw new \RuntimeException('Expected only once');
                }
            }
        };
        $objectManager = $this->createMock(EntityManagerInterface::class);

        $args = new OnFlushEventArgs($objectManager);

        $nativeDriver = $this->createMock(Driver::class);
        $dhDriver = new AuditorDriver($nativeDriver);
        $driver = new class($dhDriver) extends AbstractDriverMiddleware {};

        $objectManager
            ->method('getConnection')
            ->willReturn($connection = $this->createMock(ConnectionDbal::class))
        ;

        $connection
            ->method('getDriver')
            ->willReturn($driver)
        ;

        $target = new DoctrineSubscriber($transactionManager, $objectManager);
        $target->onFlush($args);

        foreach ($dhDriver->getFlusherList() as $item) {
            ($item)();
        }

        $this->assertTrue(true);
    }

    public function testIssue184IfNotAbstractDriverMiddleware(): void
    {
        $transactionManager = new class implements TransactionManagerInterface {
            public function populate($transaction): void {}

            public function process($transaction): void
            {
                static $i = 0;
                ++$i;
                if ($i > 1) {
                    throw new \RuntimeException('Expected only once');
                }
            }
        };
        $objectManager = $this->createMock(EntityManagerInterface::class);

        $args = new OnFlushEventArgs($objectManager);

        $nativeDriver = $this->createMock(Driver::class);
        $auditorDriver = new AuditorDriver($nativeDriver);
        if (!interface_exists(VersionAwarePlatformDriver::class)) {
            $driver = new class($auditorDriver) implements Driver {
                public function __construct(private readonly Driver $auditorDriver) {}

                public function connect(array $params): DriverConnection
                {
                    return $this->auditorDriver->connect($params);
                }

                public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
                {
                    return $this->auditorDriver->getDatabasePlatform($versionProvider);
                }

                public function getExceptionConverter(): Driver\API\ExceptionConverter
                {
                    return new ExceptionConverter();
                }
            };
        } else {
            $driver = new class($auditorDriver) implements VersionAwarePlatformDriver {
                public function __construct(private readonly Driver $auditorDriver) {}

                public function connect(array $params): DriverConnection
                {
                    return $this->auditorDriver->connect($params);
                }

                public function getDatabasePlatform(): void {}

                public function createDatabasePlatformForVersion($version): void {}

                public function getSchemaManager(ConnectionDbal $conn, AbstractPlatform $platform): void {}

                public function getExceptionConverter(): Driver\API\ExceptionConverter
                {
                    return new ExceptionConverter();
                }
            };
        }

        $objectManager
            ->method('getConnection')
            ->willReturn($connection = $this->createMock(ConnectionDbal::class))
        ;

        $connection
            ->method('getDriver')
            ->willReturn($driver)
        ;

        $target = new DoctrineSubscriber($transactionManager, $objectManager);
        $target->onFlush($args);

        foreach ($auditorDriver->getFlusherList() as $item) {
            ($item)();
        }

        $this->assertTrue(true);
    }

    public function testIssue184Unexpected(): void
    {
        $transactionManager = new class implements TransactionManagerInterface {
            public function populate($transaction): void {}

            public function process($transaction): void
            {
                throw new \RuntimeException('Unexpected call');
            }
        };
        $objectManager = $this->createMock(EntityManagerInterface::class);

        $args = new OnFlushEventArgs($objectManager);

        $nativeDriver = $this->createMock(Driver::class);
        $auditorDriver = new AuditorDriver($nativeDriver);
        if (!interface_exists(VersionAwarePlatformDriver::class)) {
            $driver = new class($auditorDriver) implements Driver {
                public function __construct(private readonly Driver $auditorDriver) {}

                public function connect(array $params): DriverConnection
                {
                    return $this->auditorDriver->connect($params);
                }

                public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
                {
                    return $this->auditorDriver->getDatabasePlatform($versionProvider);
                }

                public function getExceptionConverter(): Driver\API\ExceptionConverter
                {
                    return new ExceptionConverter();
                }
            };
        } else {
            $driver = new class($auditorDriver) implements VersionAwarePlatformDriver {
                public function __construct(private readonly Driver $auditorDriver) {}

                public function connect(array $params): DriverConnection
                {
                    return $this->auditorDriver->connect($params);
                }

                public function getDatabasePlatform(): void {}

                public function createDatabasePlatformForVersion($version): void {}

                public function getSchemaManager(ConnectionDbal $conn, AbstractPlatform $platform): void {}

                public function getExceptionConverter(): Driver\API\ExceptionConverter
                {
                    return new ExceptionConverter();
                }
            };
        }

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

        $target = new DoctrineSubscriber($transactionManager, $objectManager);
        $target->onFlush($args);

        $this->assertTrue(true);
    }
}
