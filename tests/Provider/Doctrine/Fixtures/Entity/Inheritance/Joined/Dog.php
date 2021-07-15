<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="dog")
 */
#[ORM\Entity, ORM\Table(name: 'dog')]
class Dog extends Animal
{
}
