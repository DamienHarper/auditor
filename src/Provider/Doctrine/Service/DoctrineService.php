<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Service;

use DH\Auditor\Provider\Service\AbstractService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @deprecated since auditor 4.1, to be removed in v5.0. Use damienharper/auditor-doctrine-provider instead.
 */
abstract class DoctrineService extends AbstractService
{
    public function __construct(string $name, private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct($name);
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}
