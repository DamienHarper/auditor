<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Model;

use DH\Auditor\Model\Transaction as BaseTransaction;
use Doctrine\ORM\EntityManagerInterface;

class Transaction extends BaseTransaction
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}
