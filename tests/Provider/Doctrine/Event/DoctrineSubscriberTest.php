<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Event;

use DH\Auditor\Provider\Doctrine\Auditing\Event\DoctrineSubscriber;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Logging\SQLLogger;
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
            ->willReturn($connection = $this->createMock(Connection::class))
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
}
