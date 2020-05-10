<?php

namespace DH\Auditor\Provider\Doctrine\Service;

use DH\Auditor\Provider\Service\AbstractService;
use Doctrine\ORM\EntityManagerInterface;

abstract class DoctrineService extends AbstractService
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(string $name, EntityManagerInterface $entityManager)
    {
        parent::__construct($name);

        $this->entityManager = $entityManager;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}
