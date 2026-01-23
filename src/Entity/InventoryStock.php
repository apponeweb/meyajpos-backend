<?php

namespace App\Entity;

use App\Repository\InventoryStockRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entidad que representa el stock actual por sucursal (Caché controlado).
 */
#[ORM\Entity(repositoryClass: InventoryStockRepository::class)]
#[ORM\Table(name: 'tbd_inventory_stock')]
#[ORM\HasLifecycleCallbacks]
class InventoryStock extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'id', nullable: false)]
    private ?Branch $branch = null;

    /**
     * Relación con el producto maestro.
     * Restricción: Solo debe apuntar a productos con isInventoriable = true.
     */
    #[ORM\ManyToOne(targetEntity: MasterProduct::class)]
    #[ORM\JoinColumn(name: 'master_product_id', referencedColumnName: 'id', nullable: false)]
    private ?MasterProduct $masterProduct = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $stock = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 4, nullable: true)]
    private ?string $averageCost = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastMovementAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): self
    {
        $this->branch = $branch;
        return $this;
    }

    public function getMasterProduct(): ?MasterProduct
    {
        return $this->masterProduct;
    }

    /**
     * Setea el producto maestro asegurando la regla de negocio.
     */
    public function setMasterProduct(?MasterProduct $masterProduct): self
    {
        if ($masterProduct && method_exists($masterProduct, 'isInventoriable')) {
            if (!$masterProduct->isInventoriable()) {
                throw new \InvalidArgumentException("El producto seleccionado debe ser de tipo inventariable.");
            }
        }

        $this->masterProduct = $masterProduct;
        return $this;
    }

    public function getStock(): string
    {
        return $this->stock;
    }

    public function setStock(string $stock): self
    {
        $this->stock = $stock;
        return $this;
    }

    public function getAverageCost(): ?string
    {
        return $this->averageCost;
    }

    public function setAverageCost(?string $averageCost): self
    {
        $this->averageCost = $averageCost;
        return $this;
    }

    public function getLastMovementAt(): ?\DateTimeInterface
    {
        return $this->lastMovementAt;
    }

    public function setLastMovementAt(?\DateTimeInterface $lastMovementAt): self
    {
        $this->lastMovementAt = $lastMovementAt;
        return $this;
    }
}
