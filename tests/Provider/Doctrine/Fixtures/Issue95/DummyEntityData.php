<?php

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue95;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;

class DummyEntityData extends AbstractFixture
{

    public function load(ObjectManager $manager)
    {
        $darkVador = new DummyEntity('dark.vador');

        $manager->persist($darkVador);

        $this->addReference('dark.vador', $darkVador);

        $manager->flush();
    }
}