<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Issue33;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="shop_offer_price")
 */
#[ORM\Entity, ORM\Table(name: 'shop_offer_price')]
class ShopOfferPrice
{
    /**
     * @ORM\Id
     *
     * @ORM\ManyToOne(targetEntity="Shop", cascade={"persist", "remove"})
     *
     * @ORM\JoinColumn(name="shop_id", referencedColumnName="id", nullable=true)
     */
    #[ORM\Id, ORM\ManyToOne(targetEntity: 'Shop', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'shop_id', referencedColumnName: 'id', nullable: true)]
    private $shop;

    /**
     * @ORM\Id
     *
     * @ORM\ManyToOne(targetEntity="Offer", cascade={"persist", "remove"})
     *
     * @ORM\JoinColumn(name="offer_id", referencedColumnName="id", nullable=true)
     */
    #[ORM\Id, ORM\ManyToOne(targetEntity: 'Offer', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'offer_id', referencedColumnName: 'id', nullable: true)]
    private $offer;

    /**
     * @var float|string Decimal
     */
    private $value;

    public function __construct(Shop $shop, Offer $offer, float $value)
    {
        $this->shop = $shop;
        $this->offer = $offer;
        $this->value = $value;
    }

    public function getShop(): Shop
    {
        return $this->shop;
    }

    public function getOffer(): Offer
    {
        return $this->offer;
    }

    public function getValue(): float
    {
        return (float) $this->value;
    }

    public function setValue(float $value): void
    {
        $this->value = $value;
    }
}
