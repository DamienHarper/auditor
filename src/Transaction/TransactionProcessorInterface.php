<?php

declare(strict_types=1);

namespace DH\Auditor\Transaction;

use DH\Auditor\Model\TransactionInterface;

interface TransactionProcessorInterface
{
    public function process(TransactionInterface $transaction): void;
}
