<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Blame;

use DH\Auditor\Blame\BlameContext;
use DH\Auditor\User\User;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class BlameContextTest extends TestCase
{
    public function testToArrayWithTypeOnly(): void
    {
        $context = new BlameContext('console');

        $this->assertSame(['type' => 'console'], $context->toArray(), 'Only the type is exported when nothing else is set.');
        $this->assertNull($context->user, 'No user by default.');
    }

    public function testToArrayWithLabelAndData(): void
    {
        $context = new BlameContext('import', 'Import batch #42', ['file' => 'products.csv', 'rows' => 120]);

        $this->assertSame([
            'type' => 'import',
            'label' => 'Import batch #42',
            'data' => ['file' => 'products.csv', 'rows' => 120],
        ], $context->toArray());
    }

    public function testUserIsNotExported(): void
    {
        $user = new User('1', 'alice');
        $context = new BlameContext('messenger', 'App\Message\ImportProducts', user: $user);

        $this->assertSame($user, $context->user);
        $this->assertSame(['type' => 'messenger', 'label' => 'App\Message\ImportProducts'], $context->toArray(), 'The user is blamed, not written in via.');
    }

    public function testEmptyTypeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new BlameContext(' ');
    }
}
