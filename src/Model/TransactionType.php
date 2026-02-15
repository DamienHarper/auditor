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

    // String constants for backward compatibility and simpler usage
    public const string INSERT = 'insert';

    public const string UPDATE = 'update';

    public const string REMOVE = 'remove';

    public const string ASSOCIATE = 'associate';

    public const string DISSOCIATE = 'dissociate';
}
