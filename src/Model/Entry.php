<?php

declare(strict_types=1);

namespace DH\Auditor\Model;

/**
 * @see \DH\Auditor\Tests\Model\EntryTest
 */
final class Entry
{
    private int $id;

    private string $type;

    private string $object_id;

    private ?string $discriminator = null;

    private ?string $transaction_hash = null;

    private string $diffs;

    private int|null|string $blame_id = null;

    private ?string $blame_user = null;

    private ?string $blame_user_fqdn = null;

    private ?string $blame_user_firewall = null;

    private ?string $ip = null;

    private string $created_at;

    /**
     * Get the value of id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the value of type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the value of object_id.
     */
    public function getObjectId(): string
    {
        return $this->object_id;
    }

    /**
     * Get the value of discriminator.
     */
    public function getDiscriminator(): ?string
    {
        return $this->discriminator;
    }

    /**
     * Get the value of transaction_hash.
     */
    public function getTransactionHash(): ?string
    {
        return $this->transaction_hash;
    }

    /**
     * Get the value of blame_id.
     */
    public function getUserId(): int|null|string
    {
        return $this->blame_id;
    }

    /**
     * Get the value of blame_user.
     */
    public function getUsername(): ?string
    {
        return $this->blame_user;
    }

    public function getUserFqdn(): ?string
    {
        return $this->blame_user_fqdn;
    }

    public function getUserFirewall(): ?string
    {
        return $this->blame_user_firewall;
    }

    /**
     * Get the value of ip.
     */
    public function getIp(): ?string
    {
        return $this->ip;
    }

    /**
     * Get the value of created_at.
     */
    public function getCreatedAt(): string
    {
        return $this->created_at;
    }

    /**
     * Get diff values.
     */
    public function getDiffs(bool $includeMedadata = false): array
    {
        $diffs = $this->sort(json_decode($this->diffs, true, 512, JSON_THROW_ON_ERROR));  // @phpstan-ignore-line
        if (!$includeMedadata) {
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
