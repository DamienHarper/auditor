<?php

declare(strict_types=1);

namespace DH\Auditor\Event;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AuditEvent extends Event
{
    private array $payload;

    public function __construct(array $payload)
    {
        $payload = $this->normalizePayload($payload);

        if (!SchemaHelper::isValidPayload($payload)) {
            throw new InvalidArgumentException('Invalid payload.');
        }

        $this->payload = $payload;
    }

    final public function setPayload(array $payload): self
    {
        $payload = $this->normalizePayload($payload);

        if (!SchemaHelper::isValidPayload($payload)) {
            throw new InvalidArgumentException('Invalid payload.');
        }

        $this->payload = $payload;

        return $this;
    }

    final public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Normalize the payload by adding optional fields with default values if not present.
     */
    private function normalizePayload(array $payload): array
    {
        // extra_data is optional - default to null if not provided
        if (!\array_key_exists('extra_data', $payload)) {
            $payload['extra_data'] = null;
        }

        return $payload;
    }
}
