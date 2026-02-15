<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Attribute;

use DH\Auditor\Provider\Doctrine\Auditing\Attribute as Audit;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'audited_entity')]
#[Audit\Auditable]
class AuditedEntity
{
    public string $auditedField;

    #[Audit\Ignore]
    public string $ignoredField;

    #[Audit\Ignore]
    protected string $ignoredProtectedField;

    #[Audit\Ignore]
    private string $ignoredPrivateField;

    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;
}
