<?php

namespace DH\Auditor\Transaction;

use DH\Auditor\Model\Transaction;
use DH\Auditor\Provider\Doctrine\Audit\Transaction\TransactionHydrator;
use DH\Auditor\Provider\Doctrine\Audit\Transaction\TransactionProcessor;
use DH\Auditor\Provider\ProviderInterface;

class TransactionManager
{
    /**
     * @var TransactionProcessorInterface
     */
    private $processor;

    /**
     * @var TransactionHydratorInterface
     */
    private $hydrator;

    public function __construct(ProviderInterface $provider)
    {
        $this->processor = new TransactionProcessor($provider);
        $this->hydrator = new TransactionHydrator($provider);
    }

    public function populate(Transaction $transaction): void
    {
        $this->hydrator->hydrate($transaction);
    }

    public function process(Transaction $transaction): void
    {
        $this->processor->process($transaction);
    }
}
