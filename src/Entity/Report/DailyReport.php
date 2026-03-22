<?php

namespace App\Entity\Report;

use App\Repository\Report\DailyReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DailyReportRepository::class, readOnly: true)]
#[ORM\Table(name: 'vw_daily_report')]
class DailyReport
{
    #[ORM\Id]
    #[ORM\Column(name: 'detail_id', type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(name: 'ticket_folio', type: Types::STRING, length: 255)]
    private string $ticketFolio;

    #[ORM\Column(name: 'product_service_name', type: Types::STRING, length: 255)]
    private string $productServiceName;

    #[ORM\Column(name: 'product_service_id', type: Types::INTEGER)]
    private int $productServiceId;

    #[ORM\Column(name: 'service_type_name', type: Types::STRING, length: 255)]
    private string $serviceTypeName;

    #[ORM\Column(name: 'service_type_id', type: Types::INTEGER)]
    private int $serviceTypeId;

    #[ORM\Column(name: 'barber_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $barberName = null;

    #[ORM\Column(name: 'barber_id', type: Types::INTEGER, nullable: true)]
    private ?int $barberId = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $quantity;

    #[ORM\Column(name: 'unit_price', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $unitPrice;

    #[ORM\Column(name: 'tip_amount', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $tipAmount;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $total;

    #[ORM\Column(name: 'payment_amount', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $paymentAmount;

    #[ORM\Column(name: 'cash_change', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $cashChange;

    #[ORM\Column(name: 'payment_method', type: Types::STRING, length: 255, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(name: 'payment_method_id', type: Types::INTEGER, nullable: true)]
    private ?int $paymentMethodId = null;

    #[ORM\Column(name: 'formatted_sale_date', type: Types::STRING, length: 50)]
    private string $formattedSaleDate;

    #[ORM\Column(name: 'cash_box_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $cashBoxName = null;

    #[ORM\Column(name: 'sale_date', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $saleDate;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getCashBoxName(): ?string
    {
        return $this->cashBoxName;
    }

    public function setCashBoxName(?string $cashBoxName): void
    {
        $this->cashBoxName = $cashBoxName;
    }

    public function getTicketFolio(): string
    {
        return $this->ticketFolio;
    }

    public function setTicketFolio(string $ticketFolio): void
    {
        $this->ticketFolio = $ticketFolio;
    }

    public function getProductServiceName(): string
    {
        return $this->productServiceName;
    }

    public function setProductServiceName(string $productServiceName): void
    {
        $this->productServiceName = $productServiceName;
    }

    public function getProductServiceId(): int
    {
        return $this->productServiceId;
    }

    public function setProductServiceId(int $productServiceId): void
    {
        $this->productServiceId = $productServiceId;
    }

    public function getServiceTypeName(): string
    {
        return $this->serviceTypeName;
    }

    public function setServiceTypeName(string $serviceTypeName): void
    {
        $this->serviceTypeName = $serviceTypeName;
    }

    public function getServiceTypeId(): int
    {
        return $this->serviceTypeId;
    }

    public function setServiceTypeId(int $serviceTypeId): void
    {
        $this->serviceTypeId = $serviceTypeId;
    }

    public function getBarberName(): ?string
    {
        return $this->barberName;
    }

    public function setBarberName(?string $barberName): void
    {
        $this->barberName = $barberName;
    }

    public function getBarberId(): ?int
    {
        return $this->barberId;
    }

    public function setBarberId(?int $barberId): void
    {
        $this->barberId = $barberId;
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

    public function getTipAmount(): string
    {
        return $this->tipAmount;
    }

    public function setTipAmount(string $tipAmount): void
    {
        $this->tipAmount = $tipAmount;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): void
    {
        $this->total = $total;
    }

    public function getPaymentAmount(): string
    {
        return $this->paymentAmount;
    }

    public function setPaymentAmount(string $paymentAmount): void
    {
        $this->paymentAmount = $paymentAmount;
    }

    public function getCashChange(): string
    {
        return $this->cashChange;
    }

    public function setCashChange(string $cashChange): void
    {
        $this->cashChange = $cashChange;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getPaymentMethodId(): ?int
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(?int $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getFormattedSaleDate(): string
    {
        return $this->formattedSaleDate;
    }

    public function setFormattedSaleDate(string $formattedSaleDate): void
    {
        $this->formattedSaleDate = $formattedSaleDate;
    }

    public function getSaleDate(): \DateTimeInterface
    {
        return $this->saleDate;
    }

    public function setSaleDate(\DateTimeInterface $saleDate): void
    {
        $this->saleDate = $saleDate;
    }

}
