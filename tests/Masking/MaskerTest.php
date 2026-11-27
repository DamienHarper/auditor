<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Masking;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Masking\Masker;
use DH\Auditor\Masking\MaskStrategy;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class MaskerTest extends TestCase
{
    private const string SECRET = 'a-secret-of-at-least-thirty-two-bytes';

    public function testHiddenReplacesAnyValueButNull(): void
    {
        $masker = new Masker();

        $this->assertSame(Masker::HIDDEN_VALUE, $masker->mask(MaskStrategy::Hidden, 'secret'));
        $this->assertSame(Masker::HIDDEN_VALUE, $masker->mask(MaskStrategy::Hidden, 42));
        $this->assertSame(Masker::HIDDEN_VALUE, $masker->mask(MaskStrategy::Hidden, ['a' => 1]));
        $this->assertNull($masker->mask(MaskStrategy::Hidden, null));
    }

    public function testKeyedHashIsAnHmacOfTheValue(): void
    {
        $masker = new Masker(self::SECRET);

        $this->assertSame(hash_hmac('sha256', 'john@example.com', self::SECRET), $masker->mask(MaskStrategy::KeyedHash, 'john@example.com'));
        $this->assertSame(hash_hmac('sha256', '42', self::SECRET), $masker->mask(MaskStrategy::KeyedHash, 42));
        $this->assertSame(hash_hmac('sha256', '{"a":1}', self::SECRET), $masker->mask(MaskStrategy::KeyedHash, ['a' => 1]));
        $this->assertNull($masker->mask(MaskStrategy::KeyedHash, null));
    }

    public function testKeyedHashDependsOnTheSecret(): void
    {
        $this->assertNotSame(
            new Masker(self::SECRET)->mask(MaskStrategy::KeyedHash, 'john@example.com'),
            new Masker('another-secret-of-thirty-two-bytes')->mask(MaskStrategy::KeyedHash, 'john@example.com'),
        );
    }

    public function testKeyedHashNeedsASecret(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Masker()->mask(MaskStrategy::KeyedHash, 'john@example.com');
    }

    public function testHiddenNeedsNoSecret(): void
    {
        $this->assertSame(Masker::HIDDEN_VALUE, new Masker('')->mask(MaskStrategy::Hidden, 'john@example.com'));
    }
}
