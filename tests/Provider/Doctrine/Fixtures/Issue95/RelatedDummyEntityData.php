<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue95;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;

final class RelatedDummyEntityData extends AbstractFixture
{
    public function load(ObjectManager $manager): void
    {
        $luke = new RelatedDummyEntity($this->getReference('dark.vador', DummyEntity::class), 'luke.skywalker');

        $manager->persist($luke);
        $manager->flush();
    }
}
