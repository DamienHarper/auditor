<?php

namespace DH\Auditor\Model;

class Entry
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $object_id;

    /**
     * @var null|string
     */
    protected $discriminator;

    /**
     * @var null|string
     */
    protected $transaction_hash;

    /**
     * @var string
     */
    protected $diffs;

    /**
     * @var null|int|string
     */
    protected $blame_id;

    /**
     * @var string
     */
    protected $blame_user;

    /**
     * @var string
     */
    protected $blame_user_fqdn;

    /**
     * @var string
     */
    protected $blame_user_firewall;

    /**
     * @var string
     */
    protected $ip;

    /**
     * @var string
     */
    protected $created_at;

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
     *
     * @return null|int|string
     */
    public function getUserId()
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
     *
     * @return string
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
     * Get the value of created_at.
     *
     * @return array
     */
    public function getDiffs(): ?array
    {
        return $this->sort(json_decode($this->diffs, true));
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
