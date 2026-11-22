<?php

declare(strict_types=1);

namespace DH\Auditor\Model;

use DH\Auditor\Tests\Model\EntryTest;

/**
 * @see EntryTest
 */
final class Entry
{
    public private(set) ?int $id = null;

    public private(set) int $schemaVersion = 1;

    public private(set) string $type = '';

    public string $objectId {
        get => $this->object_id;
    }

    public private(set) ?string $discriminator = null;

    public ?string $transactionId {
        get => $this->transaction_id;
    }

    public int|string|null $userId {
        get => $this->blame_id;
    }

    /**
     * Returns the decoded blame context: username, user_fqdn, user_firewall, ip.
     */
    public ?array $blame {
        get => $this->getBlame();
    }

    /** Convenience accessor for $blame['username']. */
    public ?string $username {
        get => $this->getBlame()['username'] ?? null;
    }

    /** Convenience accessor for $blame['user_fqdn']. */
    public ?string $userFqdn {
        get => $this->getBlame()['user_fqdn'] ?? null;
    }

    /** Convenience accessor for $blame['user_firewall']. */
    public ?string $userFirewall {
        get => $this->getBlame()['user_firewall'] ?? null;
    }

    /** Convenience accessor for $blame['ip']. */
    public ?string $ip {
        get => $this->getBlame()['ip'] ?? null;
    }

    public ?array $extraData {
        get => $this->getExtraData();
    }

    public ?\DateTimeImmutable $createdAt {
        get => $this->created_at;
    }

    private string $object_id = '';

    private ?string $transaction_id = null;

    private string $diffs = '{}';

    private ?string $extra_data = null;

    private int|string|null $blame_id = null;

    /**
     * Raw JSON string from the `blame` DB column — decoded via $blame virtual property.
     */
    private ?string $blame_raw = null;

    private ?\DateTimeImmutable $created_at = null;

    /**
     * Get diff changes for the current entry.
     *
     * For entries written with schema_version >= 2 (new unified format), returns
     * the 'changes' sub-array: ['field' => ['old' => x, 'new' => y], ...].
     *
     * For legacy entries (schema_version = 1, old format), returns the raw decoded
     * diffs array as-is so that existing consumers continue to work unchanged.
     *
     * The changeset of a JSON column may be a deep diff (a nested tree whose leaves are
     * {new}, {old} or {new, old}) instead of a flat {old, new} pair. The envelope's
     * 'deep_diffs' marker lists those fields; it is not part of the returned array.
     * Use isDeepDiff() to tell both shapes apart and isDiffLeaf() to walk a deep diff.
     */
    public function getDiffs(): array
    {
        $diffs = $this->decodeJson($this->diffs);

        if ($this->schemaVersion >= 2) {
            // Association/dissociation entries have no 'changes' key — return the full diff
            // (source, target, is_owning_side, join_table) so consumers can inspect it uniformly.
            if (!\array_key_exists('changes', $diffs)) {
                return $this->sort($diffs);
            }

            $changes = \is_array($diffs['changes']) ? $diffs['changes'] : [];

            return $this->sort($changes);
        }

        // Legacy format (schema_version = 1): return raw array, stripping @source metadata
        unset($diffs['@source']);

        return $this->sort($diffs);
    }

    /**
     * Tells whether getDiffs()[$field] is a JSON deep diff rather than a field diff ({old, new} pair).
     *
     * Entries carrying the 'deep_diffs' envelope marker (schema_version >= 2) are answered
     * from it. Otherwise (legacy entries, or entries written before the marker existed),
     * the answer is inferred from the changeset structure: a non-empty array whose keys
     * are all 'new'/'old' is read as a field diff, any other array as a deep diff. A JSON
     * object whose own keys are 'new'/'old' is therefore only recognised through the marker.
     */
    public function isDeepDiff(string $field): bool
    {
        if ($this->schemaVersion >= 2) {
            $diffs = $this->decodeJson($this->diffs);

            if (\is_array($diffs['deep_diffs'] ?? null)) {
                return \in_array($field, $diffs['deep_diffs'], true);
            }
        }

        $changeset = $this->getDiffs()[$field] ?? null;

        if (!\is_array($changeset)) {
            return false;
        }

        return [] === $changeset || !self::hasOnlyDiffKeys($changeset);
    }

    /**
     * Tells whether a node found inside a deep diff is a leaf ({new}, {old} or {new, old}).
     *
     * A leaf has only 'new'/'old' keys and at least one non-array value; intermediate
     * nodes only hold arrays. Not meant for the root of a changeset: use isDeepDiff().
     */
    public static function isDiffLeaf(array $node): bool
    {
        if ([] === $node || !self::hasOnlyDiffKeys($node)) {
            return false;
        }

        return array_any($node, static fn (mixed $value): bool => !\is_array($value));
    }

    /**
     * Returns the 'source' metadata block from the diffs envelope (schema_version >= 2 only).
     *
     * Contains: id, class, label, table of the audited entity.
     * Returns null for legacy entries (schema_version = 1).
     */
    public function getDiffSource(): ?array
    {
        if ($this->schemaVersion < 2) {
            return null;
        }

        $diffs = $this->decodeJson($this->diffs);
        $source = $diffs['source'] ?? null;

        return \is_array($source) ? $this->sort($source) : null;
    }

    /**
     * Returns the 'target' metadata block for ASSOCIATE/DISSOCIATE entries (schema_version >= 2 only).
     *
     * Returns null for non-association entries or legacy entries.
     */
    public function getDiffTarget(): ?array
    {
        if ($this->schemaVersion < 2) {
            return null;
        }

        $diffs = $this->decodeJson($this->diffs);
        $target = $diffs['target'] ?? null;

        return \is_array($target) ? $target : null;
    }

    /**
     * Tells whether this entry was written by a snapshot of an existing entity (schema_version >= 2 only).
     *
     * A snapshot is an 'insert' entry whose diffs envelope carries "snapshot": true: it records the state
     * of an entity that existed before auditing started, not its creation.
     */
    public function isSnapshot(): bool
    {
        if ($this->schemaVersion < 2) {
            return false;
        }

        return true === ($this->decodeJson($this->diffs)['snapshot'] ?? null);
    }

    /**
     * Returns a flat associative array suitable for CSV/JSON/NDJSON export.
     * Blame fields are expanded from the unified blame structure for consistent output
     * regardless of schema version.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'schema_version' => $this->schemaVersion,
            'type' => $this->type,
            'object_id' => $this->objectId,
            'discriminator' => $this->discriminator,
            'transaction_id' => $this->transactionId,
            'diffs' => $this->getDiffs(),
            'blame_id' => $this->userId,
            'blame_user' => $this->username,
            'blame_user_fqdn' => $this->userFqdn,
            'blame_user_firewall' => $this->userFirewall,
            'ip' => $this->ip,
            'extra_data' => $this->extraData,
            'created_at' => $this->createdAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    public function getExtraData(): ?array
    {
        if (null === $this->extra_data) {
            return null;
        }

        return $this->decodeJson($this->extra_data);
    }

    public function getBlame(): ?array
    {
        if (null === $this->blame_raw) {
            return null;
        }

        return $this->decodeJson($this->blame_raw);
    }

    public static function fromArray(array $row): self
    {
        $entry = new self();

        // Collect v1 legacy blame columns so we can synthesize blame_raw if no
        // v2 'blame' JSON column is present in the row (un-migrated v1 tables).
        $legacyBlame = [];

        foreach ($row as $key => $value) {
            // The 'blame' DB column maps to the $blame_raw backing field (the $blame
            // virtual property uses that name, so we cannot set it directly via property_exists).
            if ('blame' === $key) {
                $entry->blame_raw = $value;

                continue;
            }

            // The 'schema_version' DB column maps to the camelCase $schemaVersion property.
            if ('schema_version' === $key) {
                $entry->schemaVersion = (int) $value;

                continue;
            }

            // v1 legacy blame columns — absorbed into the blame JSON in schema v2.
            // Collect them and synthesize blame_raw below; never assign to the
            // read-only virtual $ip property or the non-existent $blame_user* properties.
            if (\in_array($key, ['blame_user', 'blame_user_fqdn', 'blame_user_firewall', 'ip'], true)) {
                $legacyBlame[$key] = $value;

                continue;
            }

            // v1 legacy transaction_hash column — superseded by transaction_id in schema v2.
            // When no transaction_id has been set yet (un-migrated v1 table), fall back to
            // transaction_hash so that $entry->transactionId is non-null and templates can
            // display and link to the transaction stream.
            if ('transaction_hash' === $key) {
                if (null === $entry->transaction_id && null !== $value && '' !== $value) {
                    $entry->transaction_id = $value;
                }

                continue;
            }

            if (property_exists($entry, $key)) {
                $entry->{$key} = 'id' === $key ? (int) $value : $value;
            }
        }

        // If no v2 'blame' column was present but v1 individual columns were, synthesize blame_raw.
        if (null === $entry->blame_raw && [] !== $legacyBlame) {
            $blameData = array_filter([
                'username' => $legacyBlame['blame_user'] ?? null,
                'user_fqdn' => $legacyBlame['blame_user_fqdn'] ?? null,
                'user_firewall' => $legacyBlame['blame_user_firewall'] ?? null,
                'ip' => $legacyBlame['ip'] ?? null,
            ], static fn (mixed $v): bool => null !== $v);

            if ([] !== $blameData) {
                $entry->blame_raw = json_encode($blameData, JSON_THROW_ON_ERROR);
            }
        }

        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * Tells whether every key of the array is 'new' or 'old' (true for an empty array).
     */
    private static function hasOnlyDiffKeys(array $array): bool
    {
        return array_all(array_keys($array), static fn (int|string $key): bool => 'new' === $key || 'old' === $key);
    }

    /**
     * Recursively sorts an array by key, preserving the old-before-new insertion order for
     * 'old'/'new' diff leaf pairs, but still recursively sorting any array values within them.
     */
    private function sort(array $array): array
    {
        // Leaf diff pairs: exactly two keys 'old' and 'new' — keep old before new,
        // but still recurse into their array values (e.g. DiffLabel value objects).
        if (2 === \count($array) && \array_key_exists('old', $array) && \array_key_exists('new', $array)) {
            if (\is_array($array['old'])) {
                $array['old'] = $this->sort($array['old']);
            }

            if (\is_array($array['new'])) {
                $array['new'] = $this->sort($array['new']);
            }

            return $array;
        }

        ksort($array);
        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $array[$key] = $this->sort($value);
            }
        }

        return $array;
    }
}
