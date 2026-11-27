---
description: Record how a change was made (an import, a console command, a Messenger message) with BlameContext and BlameContextStack, written in the via key of the blame.
---

# Blame Context

> **Record how a change was made, not only who made it**

The [user provider](user-provider.md) tells *who* made a change. A blame context tells *how*: an import,
a console command, a Messenger message, an API token, a cron job… It is written in the `via` key of the
`blame` JSON of every audit entry, next to the user.

```json
{
  "username": "alice",
  "user_fqdn": "App\\Entity\\User",
  "user_firewall": "main",
  "ip": "10.0.0.1",
  "via": { "type": "import", "label": "Import batch #42", "data": { "file": "products.csv" } }
}
```

No schema change is needed: `via` is one more key of the `blame` JSON column (schema v2).

## 🧩 The blame context

```php
use DH\Auditor\Blame\BlameContext;

new BlameContext(
    type: 'import',                    // required: kind of context
    label: 'Import batch #42',         // optional: human readable label
    data: ['file' => 'products.csv'],  // optional: JSON encodable data
    user: null,                        // optional: user to blame instead of the user provider's one
);
```

| Property | Written in `via` | Purpose |
|----------|------------------|---------|
| `type`   | ✅ always         | Kind of context, such as `import`, `console`, `messenger`, `api_token` |
| `label`  | ✅ when set       | What the viewer shows next to the type |
| `data`   | ✅ when not empty | Anything else worth keeping |
| `user`   | ❌                | Blamed instead of the user returned by the user provider (`blame_id`, `username`, `user_fqdn`, `impersonator`) |

The `user` lets a background process blame the person it acts for, such as the user who dispatched a message,
instead of the process itself. The IP address and firewall still come from the security provider.

## 📚 The blame context stack

Contexts are pushed on a stack held by the auditor configuration; the audit entries get the **current** one
(the last pushed). Nested contexts are not chained: only the innermost is written.

```php
$stack = $auditor->getConfiguration()->getBlameContextStack();

$stack->run(new BlameContext('import', 'Import batch #42'), function () use ($entityManager): void {
    // ... create or update entities ...
    $entityManager->flush();
});
```

| Method | Description |
|--------|-------------|
| `run(BlameContext $context, callable $callback): mixed` | Pushes the context, runs the callback and pops the context, even if the callback throws |
| `push(BlameContext $context): void` | Pushes a context |
| `pop(): ?BlameContext` | Pops the current context |
| `current(): ?BlameContext` | Returns the current context, or `null` |
| `isEmpty(): bool` | Whether no context is set |
| `reset(): void` | Empties the stack |

> [!TIP]
> Prefer `run()`: a context pushed and never popped (because of an exception) would be written in the audit
> entries of every following flush. In long-running processes (Messenger workers, FrankenPHP or RoadRunner
> workers), call `reset()` between two units of work. `auditor-bundle` does it for you.

The context is captured **when the entities are flushed**, not when the audit entries are written: a context
popped before the database transaction is committed still applies to the flushes made while it was current.

## 📖 Reading it back

```php
$entry->via;               // ['type' => 'import', 'label' => 'Import batch #42', 'data' => [...]] or null
$entry->blame['via'];      // same value, from the decoded blame
$entry->toArray()['blame_via']; // exports
```

Entries written without a blame context have no `via` key (`$entry->via` is `null`).
