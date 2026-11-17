<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Attribute;

use DH\Auditor\Attribute\Auditable;
use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Tests\Fixtures\Attribute\InvalidRetentionEntity;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class AuditableTest extends TestCase
{
    public function testDefaultsHaveNoRetentionPolicy(): void
    {
        $auditable = new Auditable();

        $this->assertTrue($auditable->enabled);
        $this->assertNull($auditable->maxAge);
        $this->assertNull($auditable->maxEntries);
        $this->assertTrue($auditable->getRetentionPolicy()->isEmpty());
    }

    public function testRetentionPolicyIsExposed(): void
    {
        $auditable = new Auditable(maxAge: 'P7Y', maxEntries: 10_000);

        $policy = $auditable->getRetentionPolicy();
        $this->assertSame('P7Y', $policy->maxAge);
        $this->assertSame(10_000, $policy->maxEntries);
    }

    public function testInvalidMaxAgeFailsOnInstantiation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Auditable(maxAge: '1 year');
    }

    public function testInvalidMaxEntriesFailsOnInstantiation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Auditable(maxEntries: 0);
    }

    public function testInvalidValuesFailWhenTheAttributeIsInstantiatedThroughReflection(): void
    {
        $attribute = new \ReflectionClass(InvalidRetentionEntity::class)->getAttributes(Auditable::class)[0];

        $this->expectException(InvalidArgumentException::class);

        $attribute->newInstance();
    }
}
