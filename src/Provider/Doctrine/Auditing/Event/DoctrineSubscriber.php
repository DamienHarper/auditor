<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Event;

use DH\Auditor\Provider\Doctrine\Auditing\Logger\Logger;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\LoggerChain;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\DHDriver;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionManager;
use DH\Auditor\Provider\Doctrine\Model\Transaction;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

class DoctrineSubscriber implements EventSubscriber
{
    private TransactionManager $transactionManager;

    public function __construct(TransactionManager $transactionManager)
    {
        $this->transactionManager = $transactionManager;
    }

    /**
     * It is called inside EntityManager#flush() after the changes to all the managed entities
     * and their associations have been computed.
     *
     * @see https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/events.html#onflush
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = method_exists($args, 'getObjectManager')
            ? $args->getObjectManager()
            : $args->getEntityManager();

        $transaction = new Transaction($entityManager);
        // Populate transaction
        $this->transactionManager->populate($transaction);

        $driver = $entityManager->getConnection()->getDriver();
        if ($driver instanceof DHDriver) {
            $driver->addDHFlusher(function () use ($transaction): void {
                $this->transactionManager->process($transaction);
                $transaction->reset();
            });

            return;
        }
        trigger_deprecation('damienharper/auditor', '2.2', 'SQLLogger is deprecated. Use DHMiddleware instead');
        // extend the SQL logger
        $loggerBackup = $entityManager->getConnection()->getConfiguration()->getSQLLogger();
        $auditLogger = new Logger(function () use ($loggerBackup, $entityManager, $transaction): void {
            // flushes pending data
            $entityManager->getConnection()->getConfiguration()->setSQLLogger($loggerBackup);
            $this->transactionManager->process($transaction);
            $transaction->reset();
        });

        // Initialize a new LoggerChain with the new AuditLogger + the existing SQLLoggers.
        $loggerChain = new LoggerChain();
        if ($loggerBackup instanceof LoggerChain) {
            foreach ($loggerBackup->getLoggers() as $logger) {
                $loggerChain->addLogger($logger);
            }
        } elseif ($loggerBackup instanceof SQLLogger) {
            $loggerChain->addLogger($loggerBackup);
        }
        $loggerChain->addLogger($auditLogger);
        $entityManager->getConnection()->getConfiguration()->setSQLLogger($loggerChain);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents(): array
    {
        return [Events::onFlush];
    }
}
