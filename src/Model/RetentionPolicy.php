<?php

declare(strict_types=1);

namespace DH\Auditor\Model;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Tests\Model\RetentionPolicyTest;

/**
 * Per-entity retention policy of audit entries.
 *
 * - $maxAge: entries older than this ISO 8601 duration (e.g. "P30D", "P1Y") are eligible for deletion.
 * - $maxEntries: only the N most recent entries are kept.
 *
 * Both are optional; a policy with neither value is empty and never selects anything for deletion.
 * Values are validated on construction so that an invalid policy fails before any deletion happens.
 *
 * @see RetentionPolicyTest
 */
final readonly class RetentionPolicy
{
    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        public ?string $maxAge = null,
        public ?int $maxEntries = null,
    ) {
        if (null !== $maxAge) {
            $this->assertValidMaxAge($maxAge);
        }

        if (null !== $maxEntries && $maxEntries < 1) {
            throw new InvalidArgumentException(\sprintf('Invalid retention "maxEntries" value %d: expected a positive integer.', $maxEntries));
        }
    }

    public function isEmpty(): bool
    {
        return null === $this->maxAge && null === $this->maxEntries;
    }

    /**
     * Returns the date before which entries are eligible for deletion, or null when no max age is set.
     */
    public function getCutoff(\DateTimeImmutable $now): ?\DateTimeImmutable
    {
        if (null === $this->maxAge) {
            return null;
        }

        return $now->sub(new \DateInterval($this->maxAge));
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertValidMaxAge(string $maxAge): void
    {
        try {
            $interval = new \DateInterval($maxAge);
        } catch (\Exception) {
            throw new InvalidArgumentException(\sprintf('Invalid retention "maxAge" value "%s": expected an ISO 8601 duration (e.g. P30D, P1Y).', $maxAge));
        }

        // A zero duration would select every entry for deletion: reject it as a misconfiguration.
        $reference = new \DateTimeImmutable('@0');
        if ($reference->add($interval) <= $reference) {
            throw new InvalidArgumentException(\sprintf('Invalid retention "maxAge" value "%s": the duration must be greater than zero.', $maxAge));
        }
    }
}
