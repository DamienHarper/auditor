<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Model\Transaction;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\DummyEntity;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
final class Issue44Test extends TestCase
{
    use ReaderTrait;
    use SchemaSetupTrait;

    public function testIssue44(): void
    {
        $reader = $this->createReader();

        $em = $this->provider->getStorageServiceForEntity(DummyEntity::class)->getEntityManager();
        $em->beginTransaction();
        $entity = new DummyEntity();
        $entity->setLabel('entity1');
        $em->persist($entity);
        $em->flush();
        //        $em->flush();
        $em->commit();
        //        for ($n = 1; $n <= 10; ++$n) {
        //            $em->beginTransaction();
        //            $em->commit();
        //        }

        $audits = $reader->createQuery(DummyEntity::class)->execute();
        self::assertCount(1, $audits, 'results count ok.');
        self::assertSame(Transaction::INSERT, $audits[0]->getType(), 'Reader::INSERT operation.');
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            DummyEntity::class => ['enabled' => true],
        ]);
    }
}
