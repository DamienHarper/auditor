<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue115;

enum DummyEnum: string
{
    case A = 'a';

    case B = 'b';

    public static function getOldValue(): DummyEnum
    {
        return self::A;
    }

    public static function getNewValue(): DummyEnum
    {
        return self::B;
    }
}
