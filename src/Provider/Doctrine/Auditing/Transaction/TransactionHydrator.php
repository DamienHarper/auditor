<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Transaction;

use DH\Auditor\Model\TransactionInterface;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Model\Transaction;
use DH\Auditor\Transaction\TransactionHydratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;

final class TransactionHydrator implements TransactionHydratorInterface
{
    use AuditTrait;

    public function __construct(private DoctrineProvider $provider) {}

    /**
     * @param Transaction $transaction
     */
    public function hydrate(TransactionInterface $transaction): void
    {
        $em = $transaction->getEntityManager();
        $this->hydrateWithScheduledInsertions($transaction, $em);
        $this->hydrateWithScheduledUpdates($transaction, $em);
        $this->hydrateWithScheduledDeletions($transaction, $em);
        $this->hydrateWithScheduledCollectionUpdates($transaction, $em);
        $this->hydrateWithScheduledCollectionDeletions($transaction, $em);
    }

    private function hydrateWithScheduledInsertions(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        $uow = $entityManager->getUnitOfWork();
        $entities = array_values($uow->getScheduledEntityInsertions());
        for ($i = \count($entities) - 1; $i >= 0; --$i) {
            $entity = $entities[$i];
            if ($this->provider->isAudited($entity)) {
                $transaction->insert(
                    $entity,
                    $uow->getEntityChangeSet($entity),
                );
            }
        }
    }

    private function hydrateWithScheduledUpdates(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        $uow = $entityManager->getUnitOfWork();
        $entities = array_values($uow->getScheduledEntityUpdates());
        for ($i = \count($entities) - 1; $i >= 0; --$i) {
            $entity = $entities[$i];
            if ($this->provider->isAudited($entity)) {
                $transaction->update(
                    $entity,
                    $uow->getEntityChangeSet($entity),
                );
            }
        }
    }

    private function hydrateWithScheduledDeletions(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        $uow = $entityManager->getUnitOfWork();
        $entities = array_values($uow->getScheduledEntityDeletions());
        for ($i = \count($entities) - 1; $i >= 0; --$i) {
            $entity = $entities[$i];
            if ($this->provider->isAudited($entity)) {
                $uow->initializeObject($entity);
                $transaction->remove(
                    $entity,
                    $this->id($entityManager, $entity),
                );
            }
        }
    }

    private function hydrateWithScheduledCollectionUpdates(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        $uow = $entityManager->getUnitOfWork();

        /** @var array<PersistentCollection> $collections */
        $collections = array_values($uow->getScheduledCollectionUpdates());
        for ($i = \count($collections) - 1; $i >= 0; --$i) {
            $collection = $collections[$i];
            $owner = $collection->getOwner();

            if (null !== $owner && $this->provider->isAudited($owner)) {
                $mapping = $collection->getMapping()->toArray();

                /** @var object $entity */
                foreach ($collection->getInsertDiff() as $entity) {
                    if ($this->provider->isAudited($entity)) {
                        $transaction->associate(
                            $owner,
                            $entity,
                            $mapping,
                        );
                    }
                }

                /** @var object $entity */
                foreach ($collection->getDeleteDiff() as $entity) {
                    if ($this->provider->isAudited($entity)) {
                        $transaction->dissociate(
                            $owner,
                            $entity,
                            $mapping,
                        );
                    }
                }
            }
        }
    }

    private function hydrateWithScheduledCollectionDeletions(Transaction $transaction, EntityManagerInterface $entityManager): void
    {
        $uow = $entityManager->getUnitOfWork();

        /** @var array<PersistentCollection> $collections */
        $collections = array_values($uow->getScheduledCollectionDeletions());
        for ($i = \count($collections) - 1; $i >= 0; --$i) {
            $collection = $collections[$i];
            $owner = $collection->getOwner();

            if (null !== $owner && $this->provider->isAudited($owner)) {
                $mapping = $collection->getMapping()->toArray();

                /** @var object $entity */
                foreach ($collection->toArray() as $entity) {
                    if ($this->provider->isAudited($entity)) {
                        $transaction->dissociate(
                            $owner,
                            $entity,
                            $mapping,
                        );
                    }
                }
            }
        }
    }
}
