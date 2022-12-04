<?php

declare(strict_types=1);

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

    private DoctrineProvider $provider;

    public function __construct(DoctrineProvider $provider)
    {
        $this->provider = $provider;
    }

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
        foreach (array_reverse($uow->getScheduledEntityInsertions()) as $entity) {
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
        foreach (array_reverse($uow->getScheduledEntityUpdates()) as $entity) {
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
        foreach (array_reverse($uow->getScheduledEntityDeletions()) as $entity) {
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

        /** @var PersistentCollection $collection */
        foreach (array_reverse($uow->getScheduledCollectionUpdates()) as $collection) {
            $owner = $collection->getOwner();

            if (null !== $owner && $this->provider->isAudited($owner)) {
                $mapping = $collection->getMapping();

                if (null === $mapping) {
                    continue;
                }

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

        /** @var PersistentCollection $collection */
        foreach (array_reverse($uow->getScheduledCollectionDeletions()) as $collection) {
            $owner = $collection->getOwner();

            if (null !== $owner && $this->provider->isAudited($owner)) {
                $mapping = $collection->getMapping();

                if (null === $mapping) {
                    continue;
                }

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
