<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Inheritance\Joined;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="dog", schema="auditor")
 */
#[ORM\Entity, ORM\Table(name: 'dog', schema: 'auditor')]
class Dog extends Animal {}
