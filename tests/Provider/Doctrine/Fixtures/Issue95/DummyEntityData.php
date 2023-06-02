<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue95;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;

final class DummyEntityData extends AbstractFixture
{
    public function load(ObjectManager $manager): void
    {
        $darkVador = new DummyEntity('dark.vador');

        $manager->persist($darkVador);

        $this->addReference('dark.vador', $darkVador);

        $manager->flush();
    }
}
