<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="cat")
 */
#[ORM\Entity, ORM\Table(name: 'cat')]
class Cat extends Animal
{
}
