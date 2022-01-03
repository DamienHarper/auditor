<?php

declare(strict_types=1);

namespace DH\Auditor\Event;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AuditEvent extends Event
{
    /**
     * @var array
     */
    private $payload;

    public function __construct(array $payload)
    {
        if (!SchemaHelper::isValidPayload($payload)) {
            throw new InvalidArgumentException('Invalid payload.');
        }

        $this->payload = $payload;
    }

    final public function setPayload(array $payload): Event
    {
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
}
