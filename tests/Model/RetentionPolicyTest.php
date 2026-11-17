<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Model;

use DH\Auditor\Exception\InvalidArgumentException;
use DH\Auditor\Model\RetentionPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class RetentionPolicyTest extends TestCase
{
    public function testDefaultPolicyIsEmpty(): void
    {
        $policy = new RetentionPolicy();

        $this->assertTrue($policy->isEmpty());
        $this->assertNull($policy->maxAge);
        $this->assertNull($policy->maxEntries);
        $this->assertNotInstanceOf(\DateTimeImmutable::class, $policy->getCutoff(new \DateTimeImmutable()));
    }

    public function testPolicyWithValues(): void
    {
        $policy = new RetentionPolicy('P1Y', 100);

        $this->assertFalse($policy->isEmpty());
        $this->assertSame('P1Y', $policy->maxAge);
        $this->assertSame(100, $policy->maxEntries);
    }

    public function testPolicyWithMaxEntriesOnlyIsNotEmpty(): void
    {
        $policy = new RetentionPolicy(maxEntries: 1);

        $this->assertFalse($policy->isEmpty());
        $this->assertNotInstanceOf(\DateTimeImmutable::class, $policy->getCutoff(new \DateTimeImmutable()));
    }

    public function testGetCutoffSubtractsMaxAgeFromNow(): void
    {
        $now = new \DateTimeImmutable('2026-03-31 12:00:00', new \DateTimeZone('UTC'));

        $this->assertSame(new \DateTimeImmutable('2026-03-01 12:00:00', new \DateTimeZone('UTC')), new RetentionPolicy('P30D')->getCutoff($now));
        $this->assertSame(new \DateTimeImmutable('2019-03-31 12:00:00', new \DateTimeZone('UTC')), new RetentionPolicy('P7Y')->getCutoff($now));
        $this->assertSame(new \DateTimeImmutable('2026-03-31 10:30:00', new \DateTimeZone('UTC')), new RetentionPolicy('PT1H30M')->getCutoff($now));
    }

    #[DataProvider('provideInvalidMaxAgeIsRejectedCases')]
    public function testInvalidMaxAgeIsRejected(string $maxAge): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Invalid retention "maxAge" value "%s"', $maxAge));

        new RetentionPolicy($maxAge);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidMaxAgeIsRejectedCases(): iterable
    {
        yield 'empty string' => [''];

        yield 'human readable' => ['1 year'];

        yield 'missing designator' => ['P'];

        yield 'garbage' => ['NOT_AN_INTERVAL'];

        yield 'zero days' => ['P0D'];

        yield 'zero seconds' => ['PT0S'];
    }

    #[DataProvider('provideInvalidMaxEntriesIsRejectedCases')]
    public function testInvalidMaxEntriesIsRejected(int $maxEntries): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Invalid retention "maxEntries" value %d', $maxEntries));

        new RetentionPolicy(maxEntries: $maxEntries);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideInvalidMaxEntriesIsRejectedCases(): iterable
    {
        yield 'zero' => [0];

        yield 'negative' => [-10];
    }
}
