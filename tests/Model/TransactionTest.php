<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Model;

use DH\Auditor\Model\Transaction;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class TransactionTest extends TestCase
{
    protected function setUp(): void {}

    protected function tearDown(): void {}

    public function testGetTransactionHash(): void
    {
        $transaction = new Transaction();

        $transaction_hash = $transaction->getTransactionHash();
        $this->assertNotNull($transaction_hash, 'transaction_hash is not null');
        $this->assertIsString($transaction_hash, 'transaction_hash is a string');
        $this->assertSame(40, mb_strlen($transaction_hash), 'transaction_hash is a string of 40 characters');
    }
}
