<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Traits;

trait ReflectionTrait
{
    public function reflectMethod($class, string $method, ?bool $setPublic = true): \ReflectionMethod
    {
        $reflectedClass = new \ReflectionClass($class);
        $reflectedMethod = $reflectedClass->getMethod($method);
        $reflectedMethod->setAccessible($setPublic);

        return $reflectedMethod;
    }
}
