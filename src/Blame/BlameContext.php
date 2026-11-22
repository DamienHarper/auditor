<?php

declare(strict_types=1);

namespace DH\Auditor\Blame;

use DH\Auditor\Tests\Blame\BlameContextTest;
use DH\Auditor\User\UserInterface;

/**
 * Describes how a change was made (an import, a console command, a message handler, an API token…).
 *
 * Pushed on the BlameContextStack, it is written in the `via` key of the `blame` JSON of every audit entry
 * of the flushes run while it is the current context.
 *
 * @see BlameContextTest
 */
final readonly class BlameContext
{
    /**
     * @param string               $type  kind of context, such as "import", "console", "messenger" or "api_token"
     * @param null|string          $label human readable label, such as "Import batch #42"
     * @param array<string, mixed> $data  additional JSON encodable data
     * @param null|UserInterface   $user  user to blame instead of the one returned by the user provider
     */
    public function __construct(
        public string $type,
        public ?string $label = null,
        public array $data = [],
        public ?UserInterface $user = null,
    ) {
        if ('' === mb_trim($type)) {
            throw new \InvalidArgumentException('The type of a blame context cannot be empty.');
        }
    }

    /**
     * Returns the value of the `via` key of the `blame` JSON: the type, then the label and data when set.
     *
     * @return array{type: string, label?: string, data?: array<string, mixed>}
     */
    public function toArray(): array
    {
        $via = ['type' => $this->type];

        if (null !== $this->label) {
            $via['label'] = $this->label;
        }

        if ([] !== $this->data) {
            $via['data'] = $this->data;
        }

        return $via;
    }
}
