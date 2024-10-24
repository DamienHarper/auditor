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
 */
#[Small]
final class JsonTest extends TestCase
{
    use ReaderTrait;
    use SchemaSetupTrait;

    public function testJsonInsert(): void
    {
        $storageServices = [
            DummyEntity::class => $this->provider->getStorageServiceForEntity(DummyEntity::class),
        ];

        $reader = $this->createReader();

        $dummy = new DummyEntity();
        $dummy
            ->setLabel('label')
            ->setJsonArray([
                'field1' => 'value1',
                'field2' => 'value2',
                'field3' => [
                    'field3.1' => 'value3.1',
                    'field3.2' => 'value3.2',
                ],
            ])
        ;

        $storageServices[DummyEntity::class]->getEntityManager()->persist($dummy);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(DummyEntity::class)->execute();
        $this->assertCount(1, $audits, 'results count ok.');
        $entry = array_shift($audits);
        $this->assertSame([
            'json_array' => [
                'field1' => [
                    'new' => 'value1',
                ],
                'field2' => [
                    'new' => 'value2',
                ],
                'field3' => [
                    'field3.1' => [
                        'new' => 'value3.1',
                    ],
                    'field3.2' => [
                        'new' => 'value3.2',
                    ],
                ],
            ],
            'label' => [
                'new' => 'label',
            ],
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testJsonUpdateExistingField(): void
    {
        $storageServices = [
            DummyEntity::class => $this->provider->getStorageServiceForEntity(DummyEntity::class),
        ];

        $reader = $this->createReader();

        $dummy = new DummyEntity();
        $dummy
            ->setLabel('label')
            ->setJsonArray([
                'field1' => 'value1',
                'field2' => 'value2',
                'field3' => [
                    'field3.1' => 'value3.1',
                    'field3.2' => 'value3.2',
                ],
            ])
        ;

        $storageServices[DummyEntity::class]->getEntityManager()->persist($dummy);
        $this->flushAll($storageServices);

        $dummy->setJsonArray([
            'field1' => 'new value1',
            'field2' => 'value2',
            'field3' => [
                'field3.1' => 'new value3.1',
                'field3.2' => 'value3.2',
            ],
        ]);

        $storageServices[DummyEntity::class]->getEntityManager()->persist($dummy);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(DummyEntity::class)->execute();
        $this->assertCount(2, $audits, 'results count ok.');
        $entry = array_shift($audits);
        $this->assertSame([
            'json_array' => [
                'field1' => [
                    'new' => 'new value1',
                    'old' => 'value1',
                ],
                'field3' => [
                    'field3.1' => [
                        'new' => 'new value3.1',
                        'old' => 'value3.1',
                    ],
                ],
            ],
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testJsonAddNewField(): void
    {
        $storageServices = [
            DummyEntity::class => $this->provider->getStorageServiceForEntity(DummyEntity::class),
        ];

        $reader = $this->createReader();

        $dummy = new DummyEntity();
        $dummy
            ->setLabel('label')
            ->setJsonArray([
                'field1' => 'value1',
                'field2' => 'value2',
                'field3' => [
                    'field3.1' => 'value3.1',
                    'field3.2' => 'value3.2',
                ],
            ])
        ;

        $storageServices[DummyEntity::class]->getEntityManager()->persist($dummy);
        $this->flushAll($storageServices);

        $dummy->setJsonArray([
            'field1' => 'value1',
            'field2' => 'value2',
            'field3' => [
                'field3.1' => 'value3.1',
                'field3.2' => 'value3.2',
                'field3.3' => 'value3.3',
                'field3.4' => 'value3.4',
            ],
            'field4' => 'value4',
            'field5' => 'value5',
        ]);

        $storageServices[DummyEntity::class]->getEntityManager()->persist($dummy);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(DummyEntity::class)->execute();
        $this->assertCount(2, $audits, 'results count ok.');
        $entry = array_shift($audits);
        $this->assertSame([
            'json_array' => [
                'field3' => [
                    'field3.3' => [
                        'new' => 'value3.3',
                    ],
                    'field3.4' => [
                        'new' => 'value3.4',
                    ],
                ],
                'field4' => [
                    'new' => 'value4',
                ],
                'field5' => [
                    'new' => 'value5',
                ],
            ],
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testJsonRemoveField(): void
    {
        $storageServices = [
            DummyEntity::class => $this->provider->getStorageServiceForEntity(DummyEntity::class),
        ];

        $reader = $this->createReader();

        $dummy = new DummyEntity();
        $dummy
            ->setLabel('label')
            ->setJsonArray([
                'field1' => 'value1',
                'field2' => 'value2',
                'field3' => [
                    'field3.1' => 'value3.1',
                    'field3.2' => 'value3.2',
                ],
                'field4' => [
                    'field4.1' => 'value4.1',
                    'field4.2' => 'value4.2',
                ],
            ])
        ;

        $storageServices[DummyEntity::class]->getEntityManager()->persist($dummy);
        $this->flushAll($storageServices);

        $dummy->setJsonArray([
            'field1' => 'value1',
            'field3' => [
                'field3.2' => 'value3.2',
            ],
        ]);

        $storageServices[DummyEntity::class]->getEntityManager()->persist($dummy);
        $this->flushAll($storageServices);

        $audits = $reader->createQuery(DummyEntity::class)->execute();
        $this->assertCount(2, $audits, 'results count ok.');
        $entry = array_shift($audits);
        $this->assertSame([
            'json_array' => [
                'field2' => [
                    'old' => 'value2',
                ],
                'field3' => [
                    'field3.1' => [
                        'old' => 'value3.1',
                    ],
                ],
                'field4' => [
                    'field4.1' => [
                        'old' => 'value4.1',
                    ],
                    'field4.2' => [
                        'old' => 'value4.2',
                    ],
                ],
            ],
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    private function configureEntities(): void
    {
        $this->provider->getConfiguration()->setEntities([
            DummyEntity::class => ['enabled' => true],
        ]);
    }
}
