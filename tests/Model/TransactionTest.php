<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Model;

use DH\Auditor\Model\Transaction;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[\PHPUnit\Framework\Attributes\Small]
final class TransactionTest extends TestCase
{
    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    public function testGetTransactionHash(): void
    {
        $transaction = new Transaction();

        $transaction_hash = $transaction->getTransactionHash();
        self::assertNotNull($transaction_hash, 'transaction_hash is not null');
        self::assertIsString($transaction_hash, 'transaction_hash is a string');
        self::assertSame(40, mb_strlen($transaction_hash), 'transaction_hash is a string of 40 characters');
    }
}
