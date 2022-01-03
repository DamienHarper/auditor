<?php

declare(strict_types=1);

namespace DH\Auditor\Transaction;

use DH\Auditor\Model\TransactionInterface;

interface TransactionHydratorInterface
{
    public function hydrate(TransactionInterface $transaction): void;
}
