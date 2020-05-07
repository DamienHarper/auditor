<?php

namespace DH\Auditor\Model;

interface TransactionInterface
{
    /**
     * Returns transaction hash.
     */
    public function getTransactionHash(): string;

    public function getInserted(): array;

    public function getUpdated(): array;

    public function getRemoved(): array;

    public function getAssociated(): array;

    public function getDissociated(): array;

    public function trackAuditEvent(string $type, array $data): void;
}
