<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue115;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'dummy_entity')]
class DummyEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(type: Types::BIGINT)]
    private DummyEnum $id = DummyEnum::A;

    public function getId(): DummyEnum
    {
        return $this->id;
    }

    public function setId(?DummyEnum $id): void
    {
        $this->id = $id;
    }
}
