<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Fixtures;

use Psr\Container\ContainerInterface;

/**
 * Minimal PSR-11 service locator for use in tests that need a resolver locator
 * but do not have symfony/dependency-injection available.
 *
 * @internal
 */
final class SimpleServiceLocator implements ContainerInterface
{
    /** @var array<string, object> */
    private array $instances = [];

    /**
     * @param array<string, callable> $factories Map of service ID => factory callable
     */
    public function __construct(private array $factories) {}

    public function get(string $id): object
    {
        return $this->instances[$id] ??= ($this->factories[$id])();
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }
}
