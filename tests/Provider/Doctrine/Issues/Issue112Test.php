<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Provider\Doctrine\Auditing\Transaction\AuditTrait;
use DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue112\DummyEntity;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Mapping\MappingException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
final class Issue112Test extends TestCase
{
    use AuditTrait;
    use DefaultSchemaSetupTrait;

    /**
     * @throws MappingException
     * @throws Exception
     * @throws \DH\Auditor\Exception\MappingException
     */
    public function testSummarizeWithUnusualPK(): void
    {
        $entityManager = $this->createEntityManager();
        $entity = new DummyEntity();
        $entity->setPrimaryKey(2);
        $data = $this->summarize($entityManager, $entity);
        self::assertSame('primaryKey', $data['pkName']);
    }
}
