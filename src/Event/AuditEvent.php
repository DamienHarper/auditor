<?php

declare(strict_types=1);

namespace DH\Auditor\Event;

use DH\Auditor\Exception\InvalidArgumentException;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AuditEvent extends Event
{
    /**
     * Required keys that every audit payload must contain (mirrors the audit table schema).
     */
    private const array REQUIRED_PAYLOAD_KEYS = [
        'type',
        'object_id',
        'discriminator',
        'schema_version',
        'transaction_id',
        'diffs',
        'extra_data',
        'blame_id',
        'blame',
        'created_at',
    ];

    private array $payload;

    public function __construct(array $payload)
    {
        $payload = $this->normalizePayload($payload);

        if (!$this->isValidPayload($payload)) {
            throw new InvalidArgumentException('Invalid payload.');
        }

        $this->payload = $payload;
    }

    final public function setPayload(array $payload): self
    {
        $payload = $this->normalizePayload($payload);

        if (!$this->isValidPayload($payload)) {
            throw new InvalidArgumentException('Invalid payload.');
        }

        $this->payload = $payload;

        return $this;
    }

    final public function getPayload(): array
    {
        return $this->payload;
    }

    private function isValidPayload(array $payload): bool
    {
        return array_all(self::REQUIRED_PAYLOAD_KEYS, static fn (string $key): bool => \array_key_exists($key, $payload));
    }

    /**
     * Normalize the payload by adding optional fields with default values if not present.
     */
    private function normalizePayload(array $payload): array
    {
        // extra_data is optional — default to null if not provided
        if (!\array_key_exists('extra_data', $payload)) {
            $payload['extra_data'] = null;
        }

        // blame is optional — default to null if not provided (unauthenticated contexts)
        if (!\array_key_exists('blame', $payload)) {
            $payload['blame'] = null;
        }

        // schema_version defaults to 2 (new format) if not explicitly set
        if (!\array_key_exists('schema_version', $payload)) {
            $payload['schema_version'] = 2;
        }

        return $payload;
    }
}
