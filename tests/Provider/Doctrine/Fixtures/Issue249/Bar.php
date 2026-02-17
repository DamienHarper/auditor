<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue249;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity reproducing the scenario from issue #249:
 * a ManyToOne relationship used as the @Id (foreign key as primary key).
 */
#[ORM\Entity]
#[ORM\Table(name: 'issue249_bar')]
class Bar
{
    public function __construct(
        #[ORM\Id]
        #[ORM\ManyToOne(targetEntity: Foo::class)]
        #[ORM\JoinColumn(name: 'foo_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
        private Foo $foo,
        #[ORM\Column(name: 'username', type: Types::STRING, length: 255, nullable: true)]
        private ?string $username = null
    ) {}

    public function getFoo(): Foo
    {
        return $this->foo;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }
}
