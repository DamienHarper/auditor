<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Model;

use DH\Auditor\Model\Transaction as BaseTransaction;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @deprecated since auditor 4.x, to be removed in v5.0. Use damienharper/auditor-doctrine-provider instead.
 */
final class Transaction extends BaseTransaction
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}
