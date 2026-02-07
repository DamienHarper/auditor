<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Traits;

trait ReflectionTrait
{
    public function reflectMethod($class, string $method): \ReflectionMethod
    {
        $reflectedClass = new \ReflectionClass($class);

        return $reflectedClass->getMethod($method);
    }
}
