<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Event;

use DH\Auditor\Provider\Doctrine\Auditing\DBAL\Middleware\AuditorDriver;
use DH\Auditor\Provider\Doctrine\Auditing\Event\DoctrineSubscriber;
use DH\Auditor\Tests\Provider\Doctrine\Traits\DoctrineProviderTrait;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as ConnectionDbal;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\MySQL\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
#[AllowMockObjectsWithoutExpectations]
final class DoctrineSubscriberTest extends TestCase
{
    use DoctrineProviderTrait;

    public function testIssue184IfAbstractDriverMiddleware(): void
    {
        $objectManager = $this->createMock(EntityManagerInterface::class);

        $nativeDriver = $this->createStub(Driver::class);
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

        $provider = $this->createDoctrineProvider($this->createProviderConfiguration([
            'entities' => [],
        ]));

        $target = new DoctrineSubscriber($provider, $objectManager);
        $target->onFlush(new OnFlushEventArgs($objectManager));

        foreach ($dhDriver->getFlusherList() as $item) {
            ($item)();
        }

        $this->assertTrue(true);
    }

    public function testIssue184IfNotAbstractDriverMiddleware(): void
    {
        $objectManager = $this->createMock(EntityManagerInterface::class);

        $nativeDriver = $this->createStub(Driver::class);
        $auditorDriver = new AuditorDriver($nativeDriver);
        $driver = new readonly class($auditorDriver) implements Driver {
            public function __construct(private Driver $auditorDriver) {}

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

        $objectManager
            ->method('getConnection')
            ->willReturn($connection = $this->createMock(ConnectionDbal::class))
        ;

        $connection
            ->method('getDriver')
            ->willReturn($driver)
        ;

        $provider = $this->createDoctrineProvider($this->createProviderConfiguration([
            'entities' => [],
        ]));

        $target = new DoctrineSubscriber($provider, $objectManager);
        $target->onFlush(new OnFlushEventArgs($objectManager));

        foreach ($auditorDriver->getFlusherList() as $item) {
            ($item)();
        }

        $this->assertTrue(true);
    }

    public function testOnFlushUsesEntityManagerFromEventArgs(): void
    {
        // The EM passed to the constructor is NOT the one used by onFlush.
        // onFlush must use the EM from OnFlushEventArgs so it works after
        // EntityManager resets in long-running processes.
        $constructorEm = $this->createMock(EntityManagerInterface::class);
        $eventArgsEm = $this->createMock(EntityManagerInterface::class);

        $nativeDriver = $this->createStub(Driver::class);
        $auditorDriver = new AuditorDriver($nativeDriver);

        // Only the eventArgs EM should be queried for connection/driver
        $eventArgsEm
            ->method('getConnection')
            ->willReturn($connection = $this->createMock(ConnectionDbal::class))
        ;

        $connection
            ->method('getDriver')
            ->willReturn($auditorDriver)
        ;

        // The constructor EM must NOT be called
        $constructorEm
            ->expects($this->never())
            ->method('getConnection')
        ;

        $provider = $this->createDoctrineProvider($this->createProviderConfiguration([
            'entities' => [],
        ]));

        $target = new DoctrineSubscriber($provider, $constructorEm);
        $target->onFlush(new OnFlushEventArgs($eventArgsEm));

        $this->assertCount(1, $auditorDriver->getFlusherList());
    }

    public function testIssue184Unexpected(): void
    {
        $objectManager = $this->createMock(EntityManagerInterface::class);

        $nativeDriver = $this->createStub(Driver::class);
        $auditorDriver = new AuditorDriver($nativeDriver);
        $driver = new readonly class($auditorDriver) implements Driver {
            public function __construct(private Driver $auditorDriver) {}

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

        $provider = $this->createDoctrineProvider($this->createProviderConfiguration([
            'entities' => [],
        ]));

        $target = new DoctrineSubscriber($provider, $objectManager);
        $target->onFlush(new OnFlushEventArgs($objectManager));

        $this->assertTrue(true);
    }
}
