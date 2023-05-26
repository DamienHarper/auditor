<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Event;

use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\DHDriver;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionManager;
use DH\Auditor\Provider\Doctrine\Model\Transaction;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

final class DoctrineSubscriber implements EventSubscriber
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
        $entityManager = $args->getObjectManager();
        $transaction = new Transaction($entityManager);

        // Populate transaction
        $this->transactionManager->populate($transaction);

        $driver = $entityManager->getConnection()->getDriver();
        if ($driver instanceof DHDriver) {
            $driver->addDHFlusher(function () use ($transaction): void {
                $this->transactionManager->process($transaction);
                $transaction->reset();
            });
        }
    }

    public function getSubscribedEvents(): array
    {
        return [Events::onFlush];
    }
}
