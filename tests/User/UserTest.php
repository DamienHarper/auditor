<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\User;

use DH\Auditor\User\User;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class UserTest extends TestCase
{
    public function testDefaults(): void
    {
        $user = new User('1', 'alice');

        $this->assertSame('1', $user->identifier);
        $this->assertSame('alice', $user->username);
        $this->assertNull($user->fqdn, 'No class by default.');
        $this->assertNull($user->impersonator, 'No impersonator by default.');
    }

    public function testFqdnAndImpersonator(): void
    {
        $impersonator = new User('1', 'admin', 'App\Entity\Admin');
        $user = new User('42', 'alice', 'App\Entity\Customer', $impersonator);

        $this->assertSame('App\Entity\Customer', $user->fqdn);
        $this->assertSame($impersonator, $user->impersonator);
    }
}
