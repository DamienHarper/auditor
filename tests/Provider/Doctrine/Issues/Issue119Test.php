<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Model\Entry;
use DH\Auditor\Provider\Doctrine\Auditing\Transaction\TransactionProcessor;
use DH\Auditor\Provider\Doctrine\Model\Transaction;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\DummyEntity;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class Issue119Test extends TestCase
{
    use DefaultSchemaSetupTrait;

    public function testIssue119(): void
    {
        $reader = new Reader($this->provider);
        $processor = new TransactionProcessor($this->provider);
        $entityManager = $this->createEntityManager();
        $transaction = new Transaction($entityManager);
        $entity = new DummyEntity();
        $transaction->insert($entity, [
            'json_array' => [null, [
                'example' => '例',
            ]],
        ]);
        $processor->process($transaction);
        $audits = $reader->createQuery(DummyEntity::class)->execute();
        $this->assertCount(1, $audits, 'results count ok.');

        /** @var Entry $audit */
        $audit = $audits[0];
        $diffs = $audit->getDiffs()['json_array'];
        $this->assertSame([
            'example' => '例',
        ], $diffs['new']);
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            DummyEntity::class => ['enabled' => true],
        ]);
    }
}
