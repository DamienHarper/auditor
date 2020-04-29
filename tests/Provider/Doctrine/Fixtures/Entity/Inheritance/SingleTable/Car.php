<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable;

use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable\Vehicle;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Car extends Vehicle
{
}
