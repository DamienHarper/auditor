<?php

declare(strict_types=1);

namespace DH\Auditor\History;

use DH\Auditor\Model\Entry;

/**
 * Where a change came from, derived from the blame of an audit entry.
 */
enum BlameOrigin: string
{
    /** A web request made by an authenticated user: the blame holds a firewall. */
    case Http = 'http';

    /** A console command: blame context of type "console". */
    case Console = 'console';

    /** A message handler: blame context of type "messenger". */
    case Messenger = 'messenger';

    /** Anything else: no firewall and no console or messenger context. */
    case System = 'system';

    public const string CONSOLE_CONTEXT = self::Console->value;

    public const string MESSENGER_CONTEXT = self::Messenger->value;

    /**
     * The blame context wins over the firewall: a message handled on behalf of a user carries that user, but the
     * change was made by the worker.
     */
    public static function fromEntry(Entry $entry): self
    {
        return match ($entry->via['type'] ?? null) {
            self::CONSOLE_CONTEXT => self::Console,
            self::MESSENGER_CONTEXT => self::Messenger,
            default => null !== $entry->userFirewall && '' !== $entry->userFirewall ? self::Http : self::System,
        };
    }
}
