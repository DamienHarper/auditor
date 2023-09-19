<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\SingleTable;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Bike extends Vehicle {}
