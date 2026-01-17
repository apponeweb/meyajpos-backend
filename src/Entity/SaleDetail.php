<?php

namespace App\Entity;

use App\Repository\SaleDetailRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Query\Expr\Base;

#[ORM\Entity(repositoryClass: SaleDetailRepository::class)]
#[ORM\Table(name: 'tbd_sale_detail')]
#[ORM\HasLifecycleCallbacks]
class SaleDetail extends Base
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Sale::class)]
    #[ORM\JoinColumn(name: 'sale_id', referencedColumnName: 'id', nullable: false)]
    private ?Sale $sale = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $itemLine; // Renglon

    #[ORM\ManyToOne(targetEntity: MasterProduct::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false)]
    private ?MasterProduct $product = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 3)]
    private string $quantity;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $unitPrice;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    private ?string $discount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    private string $subtotal;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    private string $tax;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    private string $total;

    /**
     * El usuario que ejecutó el servicio (ej. Barbero).
     * Clave para el cálculo de comisiones.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'service_provider_id', referencedColumnName: 'id', nullable: true)]
    private ?User $serviceProvider = null;

    #[ORM\Column(type: Types::STRING, length: 250, nullable: true)]
    private ?string $observations = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getSale(): ?Sale
    {
        return $this->sale;
    }

    public function setSale(?Sale $sale): void
    {
        $this->sale = $sale;
    }

    public function getItemLine(): int
    {
        return $this->itemLine;
    }

    public function setItemLine(int $itemLine): void
    {
        $this->itemLine = $itemLine;
    }

    public function getProduct(): ?MasterProduct
    {
        return $this->product;
    }

    public function setProduct(?MasterProduct $product): void
    {
        $this->product = $product;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function getDiscount(): ?string
    {
        return $this->discount;
    }

    public function setDiscount(?string $discount): void
    {
        $this->discount = $discount;
    }

    public function getSubtotal(): string
    {
        return $this->subtotal;
    }

    public function setSubtotal(string $subtotal): void
    {
        $this->subtotal = $subtotal;
    }

    public function getTax(): string
    {
        return $this->tax;
    }

    public function setTax(string $tax): void
    {
        $this->tax = $tax;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): void
    {
        $this->total = $total;
    }

    public function getServiceProvider(): ?User
    {
        return $this->serviceProvider;
    }

    public function setServiceProvider(?User $serviceProvider): void
    {
        $this->serviceProvider = $serviceProvider;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): void
    {
        $this->observations = $observations;
    }


}
