<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\History;

use DH\Auditor\History\RawFieldLabelResolver;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class RawFieldLabelResolverTest extends TestCase
{
    public function testItReturnsTheFieldName(): void
    {
        $this->assertSame('firstName', new RawFieldLabelResolver()->resolve('App\Entity\Author', 'firstName'));
    }
}
