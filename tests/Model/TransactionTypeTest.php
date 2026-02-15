<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Model;

use DH\Auditor\Model\TransactionType;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class TransactionTypeTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame('insert', TransactionType::Insert->value);
        $this->assertSame('update', TransactionType::Update->value);
        $this->assertSame('remove', TransactionType::Remove->value);
        $this->assertSame('associate', TransactionType::Associate->value);
        $this->assertSame('dissociate', TransactionType::Dissociate->value);
    }

    public function testEnumCases(): void
    {
        $cases = TransactionType::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(TransactionType::Insert, $cases);
        $this->assertContains(TransactionType::Update, $cases);
        $this->assertContains(TransactionType::Remove, $cases);
        $this->assertContains(TransactionType::Associate, $cases);
        $this->assertContains(TransactionType::Dissociate, $cases);
    }

    public function testEnumFromValue(): void
    {
        $this->assertSame(TransactionType::Insert, TransactionType::from('insert'));
        $this->assertSame(TransactionType::Update, TransactionType::from('update'));
        $this->assertSame(TransactionType::Remove, TransactionType::from('remove'));
        $this->assertSame(TransactionType::Associate, TransactionType::from('associate'));
        $this->assertSame(TransactionType::Dissociate, TransactionType::from('dissociate'));
    }

    public function testEnumTryFromInvalidValue(): void
    {
        $this->assertNull(TransactionType::tryFrom('invalid'));
    }
}
