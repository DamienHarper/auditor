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

    public private(set) string $type = '';

    public string $objectId {
        get => $this->object_id;
    }

    public private(set) ?string $discriminator = null;

    public ?string $transactionHash {
        get => $this->transaction_hash;
    }

    public int|string|null $userId {
        get => $this->blame_id;
    }

    public ?string $username {
        get => $this->blame_user;
    }

    public ?string $userFqdn {
        get => $this->blame_user_fqdn;
    }

    public ?string $userFirewall {
        get => $this->blame_user_firewall;
    }

    public private(set) ?string $ip = null;

    public ?array $extraData {
        get => $this->getExtraData();
    }

    public ?\DateTimeImmutable $createdAt {
        get => $this->created_at;
    }

    private string $object_id = '';

    private ?string $transaction_hash = null;

    private string $diffs = '{}';

    private ?string $extra_data = null;

    private int|string|null $blame_id = null;

    private ?string $blame_user = null;

    private ?string $blame_user_fqdn = null;

    private ?string $blame_user_firewall = null;

    private ?\DateTimeImmutable $created_at = null;

    /**
     * Returns a typed collection of field-level diffs for this audit entry.
     *
     * For INSERT and UPDATE entries, iterate the collection to access individual {@see AuditDiff} objects.
     * For REMOVE entries, the collection is empty — access {@see AuditDiffCollection::$entitySnapshot} instead.
     * For ASSOCIATE/DISSOCIATE entries, the collection is empty — access {@see AuditDiffCollection::$relationDescriptor} instead.
     */
    public function getDiffs(bool $includeMetadata = false): AuditDiffCollection
    {
        $diffs = $this->sort(json_decode($this->diffs, true, 512, JSON_THROW_ON_ERROR));  // @phpstan-ignore-line
        if (!$includeMetadata) {
            unset($diffs['@source']);
        }

        return AuditDiffCollection::fromRawDiffs($diffs, TransactionType::tryFrom($this->type) ?? TransactionType::Insert);
    }

    public function getExtraData(): ?array
    {
        if (null === $this->extra_data) {
            return null;
        }

        return json_decode($this->extra_data, true, 512, JSON_THROW_ON_ERROR);
    }

    public static function fromArray(array $row): self
    {
        $entry = new self();

        foreach ($row as $key => $value) {
            if (property_exists($entry, $key)) {
                $entry->{$key} = 'id' === $key ? (int) $value : $value;
            }
        }

        return $entry;
    }

    /**
     * @param array<mixed, mixed> $array
     *
     * @return array<string, mixed>
     */
    private function sort(array $array): array
    {
        ksort($array);
        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $array[$key] = $this->sort($value);
            }
        }

        return $array;
    }
}
