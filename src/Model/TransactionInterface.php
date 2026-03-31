<?php

declare(strict_types=1);

namespace DH\Auditor\Model;

interface TransactionInterface
{
    /**
     * Returns transaction ID (ULID).
     */
    public function getTransactionId(): string;

    public function getInserted(): array;

    public function getUpdated(): array;

    public function getRemoved(): array;

    public function getAssociated(): array;

    public function getDissociated(): array;
}
