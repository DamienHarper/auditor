<?php

declare(strict_types=1);

namespace DH\Auditor\Model;

/**
 * Enum representing the type of audit transaction.
 */
enum TransactionType: string
{
    case Insert = 'insert';
    case Update = 'update';
    case Remove = 'remove';
    case Associate = 'associate';
    case Dissociate = 'dissociate';
}
