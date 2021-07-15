<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Annotation;

use DH\Auditor\Provider\Doctrine\Auditing\Annotation as Audit;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="auditable_but_unaudited_entity")
 *
 * @Audit\Auditable(enabled=false)
 * @Audit\Security(view={"ROLE1", "ROLE2"})
 */
#[ORM\Entity, ORM\Table(name: 'auditable_but_unaudited_entity')]
#[Audit\Auditable(enabled: false), Audit\Security(view: ['ROLE1', 'ROLE2'])]
class AuditableButUnauditedEntity
{
    /**
     * @var string
     */
    public $auditedField;

    /**
     * @var string
     *
     * @Audit\Ignore
     */
    #[Audit\Ignore]
    public $ignoredField;

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="integer")
     */
    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column(type: 'integer')]
    private $id;
}
