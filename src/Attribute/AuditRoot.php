<?php

declare(strict_types=1);

namespace DH\Auditor\Attribute;

use DH\Auditor\Tests\Attribute\AuditRootTest;

/**
 * Indexes the audit entries of the entity under a root entity, so that the history of the root also shows the
 * changes made to its satellites.
 *
 * The path is a chain of association properties read from the audited entity, such as "property" or
 * "visit.property". The attribute is repeatable: an entity can belong to several roots (a bid to a property and to
 * its buyer, a link to two tickets). A path whose value is null is skipped.
 *
 * @see AuditRootTest
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class AuditRoot
{
    private const string PATH_PATTERN = '/^[\p{L}_][\p{L}\p{N}_]*(?:\.[\p{L}_][\p{L}\p{N}_]*)*$/u';

    public function __construct(public string $path)
    {
        if (1 !== preg_match(self::PATH_PATTERN, $path)) {
            throw new \InvalidArgumentException(\sprintf('Invalid audit root path "%s": expected property names separated by dots.', $path));
        }
    }

    /**
     * @return list<string>
     */
    public function getSegments(): array
    {
        return explode('.', $this->path);
    }
}
