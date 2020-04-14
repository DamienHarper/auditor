<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="dog")
 */
class Dog extends Animal
{
}
