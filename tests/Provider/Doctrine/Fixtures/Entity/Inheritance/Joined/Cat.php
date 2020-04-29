<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined;

use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined\Animal;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="cat")
 */
class Cat extends Animal
{
}
