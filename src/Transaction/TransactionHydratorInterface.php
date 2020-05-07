<?php

namespace DH\Auditor\Transaction;

use DH\Auditor\Model\TransactionInterface;

interface TransactionHydratorInterface
{
    public function hydrate(TransactionInterface $transaction): void;
}
