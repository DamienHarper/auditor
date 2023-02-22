<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue40;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 *
 * @ORM\HasLifecycleCallbacks
 *
 * @ORM\Table(name="case_core")
 */
#[ORM\Entity, ORM\HasLifecycleCallbacks, ORM\Table(name: 'case_core')]
class CoreCase
{
    /**
     * @ORM\Column(type="string", name="type", length=50)
     */
    #[ORM\Column(type: 'string', name: 'type', length: 50)]
    public $type;

    /**
     * @ORM\Column(type="string", name="status", length=50)
     */
    #[ORM\Column(type: 'string', name: 'status', length: 50)]
    public $status;

    /**
     * @ORM\Column(type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column(type: 'integer')]
    private $id;
}
