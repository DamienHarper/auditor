<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue115;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="dummy_entity")
 */
#[ORM\Entity, ORM\Table(name: 'dummy_entity')]
class DummyEntity
{
    /**
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="NONE")
     *
     * @ORM\Column(type="bigint")
     */
    #[ORM\Id, ORM\GeneratedValue(strategy: 'NONE'), ORM\Column(type: 'bigint')]
    private DummyEnum $id;

    public function __construct()
    {
        $this->id = DummyEnum::A;
    }

    public function getId(): ?DummyEnum
    {
        return $this->id;
    }

    public function setId(?DummyEnum $id): void
    {
        $this->id = $id;
    }
}
