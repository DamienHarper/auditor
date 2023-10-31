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
    /** @var Transaction[] */
    private array $transactions = [];

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
        $entityManagerId = spl_object_id($entityManager);

        // cached transaction model, if it holds same EM no need to create a new one
        $transaction = ($this->transactions[$entityManagerId] ??= new Transaction($entityManager));

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
        $currentLogger = $entityManager->getConnection()->getConfiguration()->getSQLLogger();

        // current logger is not a LoggerChain, wrap it
        if (!$currentLogger instanceof LoggerChain) {
            // backup current logger
            $this->loggerBackup = $currentLogger;

            // create a new LoggerChain with the new AuditLogger
            $auditLogger = new Logger(function () use ($entityManager, $transaction): void {
                // reset logger
                $entityManager->getConnection()->getConfiguration()->setSQLLogger($this->loggerBackup);

                // flushes pending data
                $this->transactionManager->process($transaction);
                $transaction->reset();
            });

            // Initialize a new LoggerChain with the new AuditLogger + the existing SQLLoggers.
            $loggerChain = new LoggerChain();
            $loggerChain->addLogger($currentLogger);
            $loggerChain->addLogger($auditLogger);

            $entityManager->getConnection()->getConfiguration()->setSQLLogger($loggerChain);
        }
    }

    public function getSubscribedEvents(): array
    {
        return [Events::onFlush];
    }
}
