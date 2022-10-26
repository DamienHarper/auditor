<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue115;

if (\PHP_VERSION_ID < 81000) {
    class DummyEnum
    {
        public const A = 'a';
        public const B = 'b';
    }
} else {
    enum DummyEnum: string
    {
        case A = 'a';

        case B = 'b';
    }
}
