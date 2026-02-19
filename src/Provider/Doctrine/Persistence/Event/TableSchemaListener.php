<?php

declare(strict_types=1);

namespace DH\Auditor\Provider\Doctrine\Persistence\Event;

final readonly class TableSchemaListener
{
    public function loadClassMetadata(): void
    {
        // Intentionally left empty.
        //
        // This listener was previously flattening schema-qualified table names
        // (e.g. `mydb.user`) to `mydb__user` for platforms that return false for
        // supportsSchemas() (MySQL/MariaDB). That was incorrect: MySQL and MariaDB
        // support cross-database access via `database.table` dot notation, and
        // Doctrine ORM already handles this correctly out of the box.
        // Modifying the class metadata here caused Doctrine to look for a table
        // named `mydb__user` in the current database instead of `mydb.user`,
        // breaking all entity queries when a schema is configured.
        //
        // @see https://github.com/DamienHarper/auditor/issues/236
    }
}
