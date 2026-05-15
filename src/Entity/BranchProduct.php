<?php

namespace App\Entity;

use App\Repository\BranchProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BranchProductRepository::class)]
#[ORM\Table(name: 'tbd_branch_product')]
#[ORM\UniqueConstraint(name: 'uq_branch_product', columns: ['branch_id', 'product_id'])]
class BranchProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', nullable: false, onDelete: 'CASCADE')]
    private ?Branch $branch = null;

    #[ORM\ManyToOne(targetEntity: MasterProduct::class)]
    #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
    private ?MasterProduct $product = null;

    #[ORM\Column(name: 'price_override', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $priceOverride = null;

    #[ORM\Column(name: 'enabled', type: Types::BOOLEAN)]
    private bool $enabled = true;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getBranch(): ?Branch { return $this->branch; }
    public function setBranch(?Branch $branch): self { $this->branch = $branch; return $this; }

    public function getProduct(): ?MasterProduct { return $this->product; }
    public function setProduct(?MasterProduct $product): self { $this->product = $product; return $this; }

    public function getPriceOverride(): ?string { return $this->priceOverride; }
    public function setPriceOverride(?string $priceOverride): self { $this->priceOverride = $priceOverride; return $this; }

    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): self { $this->enabled = $enabled; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getEffectivePrice(): float
    {
        if ($this->priceOverride !== null) {
            return (float) $this->priceOverride;
        }
        return (float) $this->product?->getPrice();
    }
}
