<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\History;

use DH\Auditor\History\BlameOrigin;
use DH\Auditor\Model\Entry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class BlameOriginTest extends TestCase
{
    /**
     * @param null|array<string, mixed> $blame
     */
    #[DataProvider('provideFromEntryCases')]
    public function testFromEntry(?array $blame, BlameOrigin $expected): void
    {
        $entry = Entry::fromArray(['blame' => null === $blame ? null : json_encode($blame, JSON_THROW_ON_ERROR)]);

        $this->assertSame($expected, BlameOrigin::fromEntry($entry));
    }

    /**
     * @return iterable<string, array{0: null|array<string, mixed>, 1: BlameOrigin}>
     */
    public static function provideFromEntryCases(): iterable
    {
        yield 'no blame' => [null, BlameOrigin::System];

        yield 'anonymous' => [['username' => null], BlameOrigin::System];

        yield 'firewall' => [['username' => 'alice', 'user_firewall' => 'main'], BlameOrigin::Http];

        yield 'console' => [['username' => null, 'via' => ['type' => 'console', 'label' => 'app:import']], BlameOrigin::Console];

        yield 'messenger wins over firewall' => [['username' => 'alice', 'user_firewall' => 'main', 'via' => ['type' => 'messenger']], BlameOrigin::Messenger];

        yield 'other context with firewall' => [['username' => 'alice', 'user_firewall' => 'main', 'via' => ['type' => 'api_token']], BlameOrigin::Http];

        yield 'other context' => [['via' => ['type' => 'import']], BlameOrigin::System];
    }
}
