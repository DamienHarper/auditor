<?php

declare(strict_types=1);

namespace DH\Auditor\Blame;

use DH\Auditor\Tests\Blame\BlameContextStackTest;

/**
 * Stack of the blame contexts currently in force: the top one is written in the `via` key of the `blame` JSON.
 *
 * Prefer run(), which pops the context even when the callback throws: a context left on the stack would be written
 * in the audit entries of the following flushes. Long-running processes (Messenger workers, FrankenPHP or RoadRunner
 * workers) must call reset() between two units of work.
 *
 * @see BlameContextStackTest
 */
final class BlameContextStack
{
    /**
     * @var list<BlameContext>
     */
    private array $contexts = [];

    public function push(BlameContext $context): void
    {
        $this->contexts[] = $context;
    }

    /**
     * Removes and returns the current context, or returns null when the stack is empty.
     */
    public function pop(): ?BlameContext
    {
        return array_pop($this->contexts);
    }

    /**
     * Returns the current context (the last pushed one), or null when the stack is empty.
     */
    public function current(): ?BlameContext
    {
        return [] === $this->contexts ? null : $this->contexts[array_key_last($this->contexts)];
    }

    /**
     * Runs the callback with the given context pushed on the stack, and pops it afterwards, even on failure.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function run(BlameContext $context, callable $callback): mixed
    {
        $this->push($context);
        $depth = \count($this->contexts);

        try {
            return $callback();
        } finally {
            // contexts pushed and not popped by the callback are dropped together with this one
            array_splice($this->contexts, $depth - 1);
        }
    }

    public function isEmpty(): bool
    {
        return [] === $this->contexts;
    }

    /**
     * Empties the stack.
     */
    public function reset(): void
    {
        $this->contexts = [];
    }
}
