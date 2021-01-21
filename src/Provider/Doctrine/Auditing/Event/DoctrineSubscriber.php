<?php

namespace DH\Auditor\Provider\Doctrine\Auditing\Event;

use DH\Auditor\Provider\Doctrine\Auditing\Logger\Logger;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\LoggerChain;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionManager;
use DH\Auditor\Provider\Doctrine\Model\Transaction;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

class DoctrineSubscriber implements EventSubscriber
{
    /**
     * @var TransactionManager
     */
    private $transactionManager;

    /**
     * @var ?SQLLogger
     */
    private $loggerBackup;

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
        $entityManager = $args->getEntityManager();
        $transaction = new Transaction($entityManager);

        // extend the SQL logger
        $this->loggerBackup = $entityManager->getConnection()->getConfiguration()->getSQLLogger();
        $auditLogger = new Logger(function () use ($entityManager, $transaction): void {
            // flushes pending data
            $entityManager->getConnection()->getConfiguration()->setSQLLogger($this->loggerBackup);
            $this->transactionManager->process($transaction);
        });

        // Initialize a new LoggerChain with the new AuditLogger + the existing SQLLoggers.
        $loggerChain = new LoggerChain();
        $loggerChain->addLogger($auditLogger);
        if ($this->loggerBackup instanceof LoggerChain) {
            foreach ($this->loggerBackup->getLoggers() as $logger) {
                $loggerChain->addLogger($logger);
            }
        } elseif ($this->loggerBackup instanceof SQLLogger) {
            $loggerChain->addLogger($this->loggerBackup);
        }
        $entityManager->getConnection()->getConfiguration()->setSQLLogger($loggerChain);

        // Populate transaction
        $this->transactionManager->populate($transaction);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents(): array
    {
        return [Events::onFlush];
    }
}
