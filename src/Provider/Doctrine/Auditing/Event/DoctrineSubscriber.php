<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Event;

use DH\Auditor\Provider\Doctrine\Auditing\Logger\Logger;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\LoggerChain;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\DHDriver;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionManager;
use DH\Auditor\Provider\Doctrine\Model\Transaction;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

class DoctrineSubscriber implements EventSubscriber
{
    private TransactionManager $transactionManager;

    private ?SQLLogger $loggerBackup = null;

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
        $entityManager = DoctrineHelper::getEntityManagerFromOnFlushEventArgs($args);
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
        $this->loggerBackup = $entityManager->getConnection()->getConfiguration()->getSQLLogger();
        $auditLogger = new Logger(function () use ($entityManager, $transaction): void {
            // flushes pending data
            $entityManager->getConnection()->getConfiguration()->setSQLLogger($this->loggerBackup);
            $this->transactionManager->process($transaction);
            $transaction->reset();
        });

        // Initialize a new LoggerChain with the new AuditLogger + the existing SQLLoggers.
        $loggerChain = new LoggerChain();
        if ($this->loggerBackup instanceof LoggerChain) {
            foreach ($this->loggerBackup->getLoggers() as $logger) {
                $loggerChain->addLogger($logger);
            }
        } elseif ($this->loggerBackup instanceof SQLLogger) {
            $loggerChain->addLogger($this->loggerBackup);
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
