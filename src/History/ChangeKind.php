<?php

declare(strict_types=1);

namespace DH\Auditor\History;

/**
 * What happened to an entity or to one of its fields.
 */
enum ChangeKind: string
{
    /** The entity was created (insert entry). */
    case Created = 'created';

    /** The state of an entity that existed before auditing started was recorded (snapshot entry). */
    case Snapshot = 'snapshot';

    /** The entity was deleted (remove entry). */
    case Deleted = 'deleted';

    /** A field got a value it did not have. */
    case Set = 'set';

    /** A field changed from one value to another. */
    case Changed = 'changed';

    /** A field lost its value. */
    case Cleared = 'cleared';

    /** An element was added to a collection (associate entry). */
    case Added = 'added';

    /** An element was removed from a collection (dissociate entry). */
    case Removed = 'removed';

    /** A JSON field changed: the new value holds the deep diff. */
    case Json = 'json';

    /**
     * Tells whether the change concerns the whole entity rather than one of its fields.
     */
    public function isEntityLevel(): bool
    {
        return match ($this) {
            self::Created, self::Snapshot, self::Deleted => true,
            default => false,
        };
    }
}
