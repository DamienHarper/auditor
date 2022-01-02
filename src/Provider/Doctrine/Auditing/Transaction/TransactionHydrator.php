<?php

namespace DH\Auditor\Provider\Doctrine\Auditing\Transaction;

use DH\Auditor\Model\TransactionInterface;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Model\Transaction;
use DH\Auditor\Transaction\TransactionHydratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;

class TransactionHydrator implements TransactionHydratorInterface
{
    use AuditTrait;

    /**
     * @var DoctrineProvider
     */
    private $provider;

    public function __construct(DoctrineProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * @param Transaction $transaction
     */
    public function hydrate(TransactionInterface $transaction): void
    {
        $this->hydrateWithScheduledInsertions($transaction, $transaction->getEntityManager());
        $this->hydrateWithScheduledUpdates($transaction, $transaction->getEntityManager());
        $this->hydrateWithScheduledDeletions($transaction, $transaction->getEntityManager());
        $this->hydrateWithScheduledCollectionUpdates($transaction, $transaction->getEntityManager());
        $this->hydrateWithScheduledCollectionDeletions($transaction, $transaction->getEntityManager());
    }

    private function hydrateWithScheduledInsertions(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        $uow = $entityManager->getUnitOfWork();
        foreach (array_reverse($uow->getScheduledEntityInsertions()) as $entity) {
            if ($this->provider->isAudited($entity)) {
                $transaction->trackAuditEvent(Transaction::INSERT, [
                    $entity,
                    $uow->getEntityChangeSet($entity),
                ]);
            }
        }
    }

    private function hydrateWithScheduledUpdates(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        $uow = $entityManager->getUnitOfWork();
        foreach (array_reverse($uow->getScheduledEntityUpdates()) as $entity) {
            if ($this->provider->isAudited($entity)) {
                $transaction->trackAuditEvent(Transaction::UPDATE, [
                    $entity,
                    $uow->getEntityChangeSet($entity),
                ]);
            }
        }
    }

    private function hydrateWithScheduledDeletions(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        $uow = $entityManager->getUnitOfWork();
        foreach (array_reverse($uow->getScheduledEntityDeletions()) as $entity) {
            if ($this->provider->isAudited($entity)) {
                $uow->initializeObject($entity);
                $transaction->trackAuditEvent(Transaction::REMOVE, [
                    $entity,
                    $this->id($entityManager, $entity),
                ]);
            }
        }
    }

    private function hydrateWithScheduledCollectionUpdates(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        $uow = $entityManager->getUnitOfWork();
        /** @var PersistentCollection $collection */
        foreach (array_reverse($uow->getScheduledCollectionUpdates()) as $collection) {
            /** @var object $owner */
            $owner = $collection->getOwner();
            if ($this->provider->isAudited($owner)) {
                $mapping = $collection->getMapping();
                /** @var object $entity */
                foreach ($collection->getInsertDiff() as $entity) {
                    if ($this->provider->isAudited($entity)) {
                        $transaction->trackAuditEvent(Transaction::ASSOCIATE, [
                            $collection->getOwner(),
                            $entity,
                            $mapping,
                        ]);
                    }
                }
                /** @var object $entity */
                foreach ($collection->getDeleteDiff() as $entity) {
                    if ($this->provider->isAudited($entity)) {
                        $transaction->trackAuditEvent(Transaction::DISSOCIATE, [
                            $collection->getOwner(),
                            $entity,
                            $this->id($entityManager, $entity),
                            $mapping,
                        ]);
                    }
                }
            }
        }
    }

    private function hydrateWithScheduledCollectionDeletions(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        $uow = $entityManager->getUnitOfWork();
        /** @var PersistentCollection $collection */
        foreach (array_reverse($uow->getScheduledCollectionDeletions()) as $collection) {
            /** @var object $owner */
            $owner = $collection->getOwner();
            if ($this->provider->isAudited($owner)) {
                $mapping = $collection->getMapping();
                /** @var object $entity */
                foreach ($collection->toArray() as $entity) {
                    if ($this->provider->isAudited($entity)) {
                        $transaction->trackAuditEvent(Transaction::DISSOCIATE, [
                            $collection->getOwner(),
                            $entity,
                            $this->id($entityManager, $entity),
                            $mapping,
                        ]);
                    }
                }
            }
        }
    }
}
