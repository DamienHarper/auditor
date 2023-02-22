<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Annotation;

use DH\Auditor\Provider\Doctrine\Auditing\Annotation as Audit;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="audited_entity")
 *
 * @Audit\Auditable
 */
class AuditedEntity
{
    public string $auditedField;

    /**
     * @Audit\Ignore
     */
    public string $ignoredField;

    /**
     * @Audit\Ignore
     */
    protected string $ignoredProtectedField;

    /**
     * @Audit\Ignore
     */
    private string $ignoredPrivateField;

    /**
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="IDENTITY")
     *
     * @ORM\Column(type="integer")
     */
    private int $id;
}
