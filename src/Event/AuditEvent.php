<?php

namespace DH\Auditor\Event;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Provider\Doctrine\Persistence\Helper\SchemaHelper;
use Symfony\Component\EventDispatcher\Event as ComponentEvent;
use Symfony\Contracts\EventDispatcher\Event as ContractsEvent;

if (class_exists(ContractsEvent::class)) {
    abstract class AuditEvent extends ContractsEvent
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

        final public function setPayload(array $payload): ContractsEvent
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
} else {
    abstract class AuditEvent extends ComponentEvent
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

        final public function setPayload(array $payload): ComponentEvent
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
}
