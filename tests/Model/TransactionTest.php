<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Model;

use DH\Auditor\Model\Transaction;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

/**
 * @internal
 */
#[Small]
final class TransactionTest extends TestCase
{
    protected function setUp(): void {}

    protected function tearDown(): void {}

    public function testGetTransactionId(): void
    {
        $transaction = new Transaction();

        $transaction_id = $transaction->getTransactionId();
        $this->assertNotNull($transaction_id, 'transaction_id is not null');
        $this->assertIsString($transaction_id, 'transaction_id is a string');
        $this->assertSame(26, mb_strlen($transaction_id), 'transaction_id is a string of 26 characters (ULID)');
        $this->assertTrue(Ulid::isValid($transaction_id), 'transaction_id is a valid ULID');
    }

    public function testGetTransactionIdIsStable(): void
    {
        $transaction = new Transaction();

        $this->assertSame(
            $transaction->getTransactionId(),
            $transaction->getTransactionId(),
            'transaction_id is stable within the same transaction'
        );
    }

    public function testResetClearsTransactionId(): void
    {
        $transaction = new Transaction();

        $id1 = $transaction->getTransactionId();
        $transaction->reset();
        $id2 = $transaction->getTransactionId();

        $this->assertNotSame($id1, $id2, 'reset() generates a new transaction_id');
    }
}
