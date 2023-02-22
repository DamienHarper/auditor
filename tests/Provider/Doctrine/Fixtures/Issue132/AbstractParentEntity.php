<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue132;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 *
 * @ORM\Table("parent_entities")
 *
 * @ORM\InheritanceType("SINGLE_TABLE")
 *
 * @ORM\DiscriminatorColumn(name="type", type="string")
 *
 * @ORM\DiscriminatorMap({"Dummy": DummyEntity::class})
 */
#[ORM\Entity]
#[ORM\Table('parent_entities')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn('type', 'string')]
#[ORM\DiscriminatorMap(['Dummy' => DummyEntity::class])]
abstract class AbstractParentEntity
{
    /**
     * @ORM\Id
     *
     * @ORM\Column
     *
     * @ORM\GeneratedValue
     */
    #[ORM\Id]
    #[ORM\Column]
    #[ORM\GeneratedValue]
    private ?int $id = null;
}
