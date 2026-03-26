<?php

declare(strict_types=1);

namespace DH\Auditor\Model;

enum DiffKind: string
{
    /** INSERT and UPDATE operations — iterate the collection to access individual {@see AuditDiff} objects. */
    case FieldChanges = 'field_changes';

    /** REMOVE operation — access {@see AuditDiffCollection::$entitySnapshot} for the deleted entity snapshot. */
    case EntityRemoval = 'entity_removal';

    /** ASSOCIATE operation — access {@see AuditDiffCollection::$relationDescriptor} for the relation details. */
    case Associate = 'associate';

    /** DISSOCIATE operation — access {@see AuditDiffCollection::$relationDescriptor} for the relation details. */
    case Dissociate = 'dissociate';

    public function hasFieldDiffs(): bool
    {
        return self::FieldChanges === $this;
    }

    public function isRelation(): bool
    {
        return self::Associate === $this || self::Dissociate === $this;
    }

    public function isRemoval(): bool
    {
        return self::EntityRemoval === $this;
    }
}
