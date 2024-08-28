<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Auditing\Transaction;

use DH\Auditor\Model\TransactionInterface;
use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Transaction\TransactionHydratorInterface;
use DH\Auditor\Transaction\TransactionManagerInterface;
use DH\Auditor\Transaction\TransactionProcessorInterface;

final readonly class TransactionManager implements TransactionManagerInterface
{
    private TransactionProcessorInterface $processor;

    private TransactionHydratorInterface $hydrator;

    public function __construct(DoctrineProvider $provider)
    {
        $this->processor = new TransactionProcessor($provider);
        $this->hydrator = new TransactionHydrator($provider);
    }

    public function populate(TransactionInterface $transaction): void
    {
        $this->hydrator->hydrate($transaction);
    }

    public function process(TransactionInterface $transaction): void
    {
        $this->processor->process($transaction);
    }
}
