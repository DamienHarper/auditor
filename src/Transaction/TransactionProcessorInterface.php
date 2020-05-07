<?php

namespace DH\Auditor\Transaction;

use DH\Auditor\Model\TransactionInterface;

interface TransactionProcessorInterface
{
    public function process(TransactionInterface $transaction): void;
}
