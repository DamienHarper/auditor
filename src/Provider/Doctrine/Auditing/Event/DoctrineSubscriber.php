<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Event;

use Closure;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Logger;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\LoggerChain;
use DH\Auditor\Provider\Doctrine\Auditing\Logger\Middleware\DHDriver;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionManager;
use DH\Auditor\Provider\Doctrine\Model\Transaction;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\DoctrineHelper;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use ReflectionClass;

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

        if (!$driver instanceof DHDriver) {
            $driver = $this->getWrappedDriver($driver);
        }

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
        if (!$currentLogger instanceof LoggerChain) {
            $this->loggerBackup = $currentLogger;

            $auditLogger = new Logger(function () use ($entityManager, $transaction): void {
                // reset logger
                $entityManager->getConnection()->getConfiguration()->setSQLLogger($this->loggerBackup);

                // flushes pending data
                $this->transactionManager->process($transaction);
                $transaction->reset();
            });

            // Initialize a new LoggerChain with the new AuditLogger + the existing SQLLoggers.
            $loggerChain = new LoggerChain();
            if (null !== $currentLogger) {
                $loggerChain->addLogger($currentLogger);
            }
            $loggerChain->addLogger($auditLogger);

            $entityManager->getConnection()->getConfiguration()->setSQLLogger($loggerChain);
        }
    }

    public function getSubscribedEvents(): array
    {
        return [Events::onFlush];
    }

    /**
     * @internal this method is used to retrieve the wrapped driver from the given driver
     *
     * @return Closure|Driver
     */
    public function getWrappedDriver(Driver $driver)
    {
        $that = $this;

        // if the driver is already a DHDriver, return it
        if ($driver instanceof DHDriver) {
            return $driver;
        }

        // if the driver is an instance of AbstractDriverMiddleware, return the wrapped driver
        if ($driver instanceof AbstractDriverMiddleware) {
            return Closure::bind(function () use ($that) {
                // @var AbstractDriverMiddleware $this
                return $that->getWrappedDriver($this->wrappedDriver);
            }, $driver, AbstractDriverMiddleware::class)();
        }

        return Closure::bind(function () use ($that) {
            /** @var Driver $this */
            $properties = (new ReflectionClass($this))->getProperties();
            foreach ($properties as $property) {
                $property->setAccessible(true);
                $value = $property->getValue($this);
                if ($value instanceof Driver) {
                    return $that->getWrappedDriver($value);
                }
            }

            return null;
        }, $driver, Driver::class)() ?? $driver;
    }
}
