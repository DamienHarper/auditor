<?php

declare(strict_types=1);

namespace DH\Auditor\Transaction;

use DH\Auditor\Model\TransactionInterface;

interface TransactionManagerInterface
{
    public function populate(TransactionInterface $transaction): void;

    public function process(TransactionInterface $transaction): void;
}
