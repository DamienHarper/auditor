<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Issues;

use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\Persistence\Schema\SchemaManager;
use DH\Auditor\Tests\Provider\Doctrine\Traits\Schema\DefaultSchemaSetupTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
/**
 * @internal
 */
final class Issue174Test extends TestCase
{
    use DefaultSchemaSetupTrait;

    public function testIssue174(): void
    {
        $configuration = new Configuration(['table_prefix' => 'audit_', 'table_suffix' => '_suffix']);
        $manager = new SchemaManager($this->provider);
        $result = $manager->computeAuditTablename('schema.entity', $configuration);
        self::assertSame('schema.audit_entity_suffix', $result);
    }
}
