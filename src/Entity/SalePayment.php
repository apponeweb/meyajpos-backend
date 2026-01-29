<?php

namespace App\Entity;

use App\Repository\SalePaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ORM\Entity(repositoryClass: SalePaymentRepository::class)]
#[ORM\Table(name: 'tbr_sale_payment')]
#[ORM\HasLifecycleCallbacks]
class SalePayment extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;
    #[Ignore]
    #[ORM\ManyToOne(targetEntity: Sale::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Sale $sale = null;

    #[ORM\ManyToOne(targetEntity: PaymentType::class)]
    #[ORM\JoinColumn(name: 'payment_type_id', referencedColumnName: 'id', nullable: false)]
    private ?PaymentType $paymentType = null;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(name: 'currency_id', referencedColumnName: 'id', nullable: false)]
    private ?Currency $currency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 6)]
    private ?string $exchangeRateUsed = '0.000000';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private ?string $amountReceived = null;

    #[ORM\Column(name: 'amount_mn', type: Types::DECIMAL, precision: 18, scale: 2)]
    private ?string $amountLocalCurrency = '0.00';

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $reference = null;

    public function __construct()
    {
        // Valores por defecto según tus requerimientos
        $this->exchangeRateUsed = '0.000000';
        $this->amountLocalCurrency = '0.00';
    }

    #[ORM\PrePersist]
    public function setupDefaultValues(): void
    {
        if ($this->amountLocalCurrency === '0.00') {
            $this->amountLocalCurrency = $this->amountReceived;
        }

        if ($this->exchangeRateUsed === '0.000000') {
            $this->exchangeRateUsed = '1.000000';
        }
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSale(): ?Sale
    {
        return $this->sale;
    }

    public function setSale(?Sale $sale): self
    {
        $this->sale = $sale;
        return $this;
    }

    public function getPaymentType(): ?PaymentType
    {
        return $this->paymentType;
    }

    public function setPaymentType(?PaymentType $paymentType): self
    {
        $this->paymentType = $paymentType;
        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(?Currency $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getExchangeRateUsed(): ?string
    {
        return $this->exchangeRateUsed;
    }

    public function setExchangeRateUsed(string $exchangeRateUsed): self
    {
        $this->exchangeRateUsed = $exchangeRateUsed;
        return $this;
    }

    public function getAmountReceived(): ?string
    {
        return $this->amountReceived;
    }

    public function setAmountReceived(string $amountReceived): self
    {
        $this->amountReceived = $amountReceived;
        return $this;
    }

    public function getAmountLocalCurrency(): ?string
    {
        return $this->amountLocalCurrency;
    }

    public function setAmountLocalCurrency(string $amountLocalCurrency): self
    {
        $this->amountLocalCurrency = $amountLocalCurrency;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }
}
