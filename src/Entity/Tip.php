<?php

namespace App\Entity;

use App\Repository\TipRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

#[ORM\Entity(repositoryClass: TipRepository::class)]
#[ORM\Table(name: 'tbn_tip')]
class Tip extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'tip_id')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SalePayment::class)]
    #[ORM\JoinColumn(name: 'sale_payment_id', referencedColumnName: 'id', nullable: false)]
    private ?SalePayment $salePayment = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: PaymentType::class)]
    #[ORM\JoinColumn(name: 'payment_type_id', referencedColumnName: 'id', nullable: false)]
    private ?PaymentType $paymentType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $tipDate = null;

    public function __construct()
    {
        // Inicializamos la fecha por defecto al crear la instancia
        $this->tipDate = new \DateTime();
        // Si BaseEntity maneja el estado activo por defecto, no hace falta repetirlo aquí
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSalePayment(): ?SalePayment
    {
        return $this->salePayment;
    }

    public function setSalePayment(?SalePayment $salePayment): self
    {
        $this->salePayment = $salePayment;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
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

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getTipDate(): string
    {
        return $this->tipDate?->format('d/m/Y H:i:s');
    }

    public function setTipDate(?\DateTimeInterface $tipDate): void
    {
        $this->tipDate = $tipDate;
    }


}
