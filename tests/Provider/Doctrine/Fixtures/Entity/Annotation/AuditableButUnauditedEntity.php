<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Annotation;

use DH\Auditor\Provider\Doctrine\Audit\Annotation as Audit;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="auditable_but_unaudited_entity")
 *
 * @Audit\Auditable(enabled=false)
 * @Audit\Security(view={"ROLE1", "ROLE2"})
 */
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
    public $ignoredField;
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="integer")
     */
    private $id;
}
