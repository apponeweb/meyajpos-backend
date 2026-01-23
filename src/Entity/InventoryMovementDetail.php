<?php

namespace App\Entity;

use App\Repository\InventoryMovementDetailRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entidad de detalle (Kardex) para movimientos de inventario.
 */
#[ORM\Entity(repositoryClass: InventoryMovementDetailRepository::class)]
#[ORM\Table(name: 'tbr_inventory_movement_detail')]
#[ORM\HasLifecycleCallbacks]
class InventoryMovementDetail extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InventoryMovement::class)]
    #[ORM\JoinColumn(name: 'inventory_movement_id', referencedColumnName: 'id', nullable: false)]
    private ?InventoryMovement $inventoryMovement = null;

    /**
     * Relación con MasterProduct.
     * Nota: Solo productos inventariables deben registrarse aquí.
     */
    #[ORM\ManyToOne(targetEntity: MasterProduct::class)]
    #[ORM\JoinColumn(name: 'master_product_id', referencedColumnName: 'id', nullable: false)]
    private ?MasterProduct $masterProduct = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $quantity;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 4, nullable: true)]
    private ?string $unitCost = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 4, nullable: true)]
    private ?string $totalAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $stockBefore;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $stockAfter;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInventoryMovement(): ?InventoryMovement
    {
        return $this->inventoryMovement;
    }

    public function setInventoryMovement(?InventoryMovement $inventoryMovement): self
    {
        $this->inventoryMovement = $inventoryMovement;
        return $this;
    }

    public function getMasterProduct(): ?MasterProduct
    {
        return $this->masterProduct;
    }

    public function setMasterProduct(?MasterProduct $masterProduct): self
    {
        $this->masterProduct = $masterProduct;
        return $this;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getUnitCost(): ?string
    {
        return $this->unitCost;
    }

    public function setUnitCost(?string $unitCost): self
    {
        $this->unitCost = $unitCost;
        return $this;
    }

    public function getTotalAmount(): ?string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(?string $totalAmount): self
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function getStockBefore(): string
    {
        return $this->stockBefore;
    }

    public function setStockBefore(string $stockBefore): self
    {
        $this->stockBefore = $stockBefore;
        return $this;
    }

    public function getStockAfter(): string
    {
        return $this->stockAfter;
    }

    public function setStockAfter(string $stockAfter): self
    {
        $this->stockAfter = $stockAfter;
        return $this;
    }
}
