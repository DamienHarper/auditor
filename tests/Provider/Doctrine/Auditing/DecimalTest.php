<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Auditing;

use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\DummyEntity;
use DH\Auditor\Tests\Provider\Doctrine\Traits\ReaderTrait;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\SchemaSetupTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @see https://github.com/DamienHarper/auditor/issues/278
 */
#[Small]
final class DecimalTest extends TestCase
{
    use ReaderTrait;
    use SchemaSetupTrait;

    /**
     * Reproduces issue #299: a decimal value re-saved with a different string format
     * (but same numeric value) must not generate a false positive audit entry.
     *
     * Typical scenario: MoneyType/NumberType submits "60" while the DB stored "60.00".
     */
    public function testNoFalsePositiveWhenDecimalValueFormatDiffersButValueIsTheSame(): void
    {
        $storageServices = [
            DummyEntity::class => $this->provider->getStorageServiceForEntity(DummyEntity::class),
        ];
        $entityManager = $storageServices[DummyEntity::class]->getEntityManager();
        $reader = $this->createReader();

        // Step 1: insert entity with decimal "60.00"
        $dummy = new DummyEntity();
        $dummy
            ->setLabel('test')
            ->setDecimalValue('60.00')
        ;
        $entityManager->persist($dummy);
        $this->flushAll($storageServices);

        // Step 2: simulate a form re-submission where MoneyType/NumberType returns
        // the same numeric value but without trailing zeros ("60" instead of "60.00")
        $dummy->setDecimalValue('60');
        $entityManager->persist($dummy);
        $this->flushAll($storageServices);

        // Only 1 audit entry should exist (the INSERT); no false UPDATE
        $audits = $reader->createQuery(DummyEntity::class)->execute();
        $this->assertCount(
            1,
            $audits,
            'No false positive UPDATE audit entry when decimal format differs but numeric value is the same.'
        );
    }

    /**
     * Ensure that a real decimal value change IS properly audited.
     */
    public function testDecimalUpdateIsAuditedWhenValueActuallyChanges(): void
    {
        $storageServices = [
            DummyEntity::class => $this->provider->getStorageServiceForEntity(DummyEntity::class),
        ];
        $entityManager = $storageServices[DummyEntity::class]->getEntityManager();
        $reader = $this->createReader();

        // Step 1: insert entity with decimal "60.00"
        $dummy = new DummyEntity();
        $dummy
            ->setLabel('test')
            ->setDecimalValue('60.00')
        ;
        $entityManager->persist($dummy);
        $this->flushAll($storageServices);

        // Step 2: actually change the decimal value
        $dummy->setDecimalValue('60.50');
        $entityManager->persist($dummy);
        $this->flushAll($storageServices);

        // 2 audit entries should exist: INSERT + UPDATE
        $audits = $reader->createQuery(DummyEntity::class)->execute();
        $this->assertCount(2, $audits, 'UPDATE audit entry created when decimal value actually changes.');

        $updateEntry = array_shift($audits);
        $this->assertSame([
            'decimal_value' => [
                'new' => '60.5',
                'old' => '60',
            ],
        ], $updateEntry->getDiffs(), 'Diff shows the correct old/new decimal values.');
    }

    /**
     * Additional format variants that should all be considered equal to "60.00".
     */
    public function testNoFalsePositiveForVariousEquivalentFormats(): void
    {
        $storageServices = [
            DummyEntity::class => $this->provider->getStorageServiceForEntity(DummyEntity::class),
        ];
        $entityManager = $storageServices[DummyEntity::class]->getEntityManager();
        $reader = $this->createReader();

        $dummy = new DummyEntity();
        $dummy->setLabel('test')->setDecimalValue('60.00');
        $entityManager->persist($dummy);
        $this->flushAll($storageServices);

        // "60.0" is numerically equal to "60.00"
        $dummy->setDecimalValue('60.0');
        $entityManager->persist($dummy);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(DummyEntity::class)->execute();
        $this->assertCount(1, $audits, 'No false positive for "60.0" vs "60.00".');
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            DummyEntity::class => ['enabled' => true],
        ]);
    }
}
