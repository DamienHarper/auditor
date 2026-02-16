<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue101;

use DH\Auditor\Provider\Doctrine\Auditing\Attribute as Audit;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
#[ORM\Table(name: 'entity')]
#[Audit\Auditable]
class ParentEntity
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
