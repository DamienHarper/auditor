<?php

namespace DH\Auditor\Provider\Doctrine\Model;

use DH\Auditor\Model\Transaction as BaseTransaction;
use Doctrine\ORM\EntityManagerInterface;

class Transaction extends BaseTransaction
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}
