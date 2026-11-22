<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Blame;

use DH\Auditor\Blame\BlameContext;
use DH\Auditor\Blame\BlameContextStack;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class BlameContextStackTest extends TestCase
{
    public function testEmptyByDefault(): void
    {
        $stack = new BlameContextStack();

        $this->assertTrue($stack->isEmpty());
        $this->assertNull($stack->current());
        $this->assertNull($stack->pop());
    }

    public function testPushAndPop(): void
    {
        $stack = new BlameContextStack();
        $console = new BlameContext('console', 'app:import');
        $import = new BlameContext('import', 'Import batch #42');

        $stack->push($console);
        $stack->push($import);

        $this->assertSame($import, $stack->current(), 'The last pushed context is the current one.');
        $this->assertSame($import, $stack->pop());
        $this->assertSame($console, $stack->current(), 'The previous context is restored.');
        $this->assertSame($console, $stack->pop());
        $this->assertTrue($stack->isEmpty());
    }

    public function testRun(): void
    {
        $stack = new BlameContextStack();
        $context = new BlameContext('import');

        $result = $stack->run($context, function () use ($stack, $context): string {
            $this->assertSame($context, $stack->current(), 'The context is current while the callback runs.');

            return 'done';
        });

        $this->assertSame('done', $result, 'run() returns the result of the callback.');
        $this->assertTrue($stack->isEmpty(), 'The context is popped after the callback.');
    }

    public function testRunPopsTheContextWhenTheCallbackThrows(): void
    {
        $stack = new BlameContextStack();
        $outer = new BlameContext('console');
        $stack->push($outer);

        try {
            $stack->run(new BlameContext('import'), static function (): never {
                throw new \RuntimeException('Import failed');
            });
            $this->fail('The exception is rethrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Import failed', $e->getMessage());
        }

        $this->assertSame($outer, $stack->current(), 'The outer context is current again.');
    }

    public function testRunDropsTheContextsLeftByTheCallback(): void
    {
        $stack = new BlameContextStack();

        $stack->run(new BlameContext('import'), static function () use ($stack): void {
            $stack->push(new BlameContext('leaked'));
        });

        $this->assertTrue($stack->isEmpty(), 'A context pushed and not popped by the callback does not leak.');
    }

    public function testReset(): void
    {
        $stack = new BlameContextStack();
        $stack->push(new BlameContext('console'));
        $stack->push(new BlameContext('import'));

        $stack->reset();

        $this->assertTrue($stack->isEmpty());
    }
}
