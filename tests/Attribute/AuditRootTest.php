<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Attribute;

use DH\Auditor\Attribute\AuditRoot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class AuditRootTest extends TestCase
{
    public function testSegments(): void
    {
        $this->assertSame(['property'], new AuditRoot('property')->getSegments());
        $this->assertSame(['visit', 'property'], new AuditRoot('visit.property')->getSegments());
    }

    public function testIsRepeatable(): void
    {
        $reflection = new \ReflectionClass(AuditRoot::class);
        $attribute = $reflection->getAttributes(\Attribute::class)[0]->newInstance();

        $this->assertSame(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE, $attribute->flags);
    }

    #[DataProvider('provideInvalidPathCases')]
    public function testInvalidPath(string $path): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AuditRoot($path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidPathCases(): iterable
    {
        yield 'empty' => [''];

        yield 'leading dot' => ['.property'];

        yield 'trailing dot' => ['property.'];

        yield 'double dot' => ['visit..property'];

        yield 'space' => ['visit property'];

        yield 'digit first' => ['1property'];
    }
}
