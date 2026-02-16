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

    public ?\DateTimeImmutable $createdAt {
        get => $this->created_at;
    }

    private string $object_id = '';

    private ?string $transaction_hash = null;

    private string $diffs = '{}';

    private int|string|null $blame_id = null;

    private ?string $blame_user = null;

    private ?string $blame_user_fqdn = null;

    private ?string $blame_user_firewall = null;

    private ?\DateTimeImmutable $created_at = null;

    /**
     * Get diff values.
     */
    public function getDiffs(bool $includeMetadata = false): array
    {
        $diffs = $this->sort(json_decode($this->diffs, true, 512, JSON_THROW_ON_ERROR));  // @phpstan-ignore-line
        if (!$includeMetadata) {
            unset($diffs['@source']);
        }

        return $diffs;
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
