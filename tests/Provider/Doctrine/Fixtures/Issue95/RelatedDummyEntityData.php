<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue95;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;

class RelatedDummyEntityData extends AbstractFixture
{

    public function load(ObjectManager $manager)
    {
        $luke = new RelatedDummyEntity($this->getReference('dark.vador'), 'luke.skywalker');

        $manager->persist($luke);
        $manager->flush();
    }
}