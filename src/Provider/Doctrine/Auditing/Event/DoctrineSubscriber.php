<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Event;

use DH\Auditor\Provider\Doctrine\Auditing\DBAL\Middleware\AuditorDriver;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\AuditTrait;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Model\Transaction;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;

final class DoctrineSubscriber
{
    use AuditTrait;

    /** @var Transaction[] */
    private array $transactions = [];

    public function __construct(
        private readonly DoctrineProvider $provider,
        private readonly EntityManagerInterface $entityManager
    ) {}

    /**
     * It is called inside EntityManager#flush() after the changes to all the managed entities
     * and their associations have been computed.
     *
     * @see https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/events.html#onflush
     */
    public function onFlush(): void
    {
        $entityManagerId = spl_object_id($this->entityManager);
        // cached transaction model, if it holds same EM no need to create a new one
        $transaction = ($this->transactions[$entityManagerId] ??= new Transaction($this->entityManager));
        // Populate transaction
        $this->provider->getTransactionManager()->populate($transaction);
        $driver = $this->entityManager->getConnection()->getDriver();
        if (!$driver instanceof AuditorDriver) {
            $driver = $this->getWrappedDriver($driver);
        }

        if ($driver instanceof AuditorDriver) {
            $driver->addFlusher(function () use ($transaction): void {
                $this->provider->getTransactionManager()->process($transaction);
                $transaction->reset();
            });
        }
    }

    public function postSoftDelete(LifecycleEventArgs $args): void
    {
        $entityManagerId = spl_object_id($this->entityManager);

        // cached transaction model, if it holds same EM no need to create a new one
        $transaction = ($this->transactions[$entityManagerId] ??= new Transaction($this->entityManager));

        if ($this->provider->isAudited($args->getObject())) {
            $transaction->remove(
                $args->getObject(),
                $this->id($this->entityManager, $args->getObject()),
            );
        }
    }

    /**
     * @internal this method is used to retrieve the wrapped driver from the given driver
     */
    public function getWrappedDriver(Driver $driver): \Closure|Driver
    {
        $that = $this;

        // if the driver is already a DHDriver, return it
        if ($driver instanceof AuditorDriver) {
            return $driver;
        }

        // if the driver is an instance of AbstractDriverMiddleware, return the wrapped driver
        if ($driver instanceof AbstractDriverMiddleware) {
            return \Closure::bind(fn (): \Closure|\Doctrine\DBAL\Driver
                // @var AbstractDriverMiddleware $this
                => $that->getWrappedDriver($this->wrappedDriver), $driver, AbstractDriverMiddleware::class)();
        }

        return \Closure::bind(function () use ($that): \Closure|Driver|null {
            /** @var Driver $this */
            $properties = new \ReflectionClass($this)->getProperties();
            foreach ($properties as $property) {
                $value = $property->getValue($this);
                if ($value instanceof Driver) {
                    return $that->getWrappedDriver($value);
                }
            }

            return null;
        }, $driver, Driver::class)() ?: $driver;
    }
}
