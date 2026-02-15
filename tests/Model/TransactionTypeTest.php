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
    public function testConstants(): void
    {
        $this->assertSame('insert', TransactionType::INSERT);
        $this->assertSame('update', TransactionType::UPDATE);
        $this->assertSame('remove', TransactionType::REMOVE);
        $this->assertSame('associate', TransactionType::ASSOCIATE);
        $this->assertSame('dissociate', TransactionType::DISSOCIATE);
    }

    public function testConstantsMatchCaseValues(): void
    {
        $this->assertSame(TransactionType::INSERT, TransactionType::Insert->value);
        $this->assertSame(TransactionType::UPDATE, TransactionType::Update->value);
        $this->assertSame(TransactionType::REMOVE, TransactionType::Remove->value);
        $this->assertSame(TransactionType::ASSOCIATE, TransactionType::Associate->value);
        $this->assertSame(TransactionType::DISSOCIATE, TransactionType::Dissociate->value);
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
        $this->assertSame(TransactionType::Insert, TransactionType::from(TransactionType::INSERT));
        $this->assertSame(TransactionType::Update, TransactionType::from(TransactionType::UPDATE));
        $this->assertSame(TransactionType::Remove, TransactionType::from(TransactionType::REMOVE));
        $this->assertSame(TransactionType::Associate, TransactionType::from(TransactionType::ASSOCIATE));
        $this->assertSame(TransactionType::Dissociate, TransactionType::from(TransactionType::DISSOCIATE));
    }

    public function testEnumTryFromInvalidValue(): void
    {
        $this->assertNull(TransactionType::tryFrom('invalid'));
    }
}
