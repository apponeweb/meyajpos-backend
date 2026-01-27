<?php

namespace App\Entity;

use App\Enum\SaleStatus;
use App\Repository\SaleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SaleRepository::class)]
#[ORM\Table(name: 'tbd_sale')]
#[UniqueEntity(fields: ['folio'], message: 'Ya existe un registro con este folio.')]
#[ORM\HasLifecycleCallbacks]
class Sale extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CashBox::class)]
    #[ORM\JoinColumn(name: 'cash_box_id', referencedColumnName: 'id', nullable: false)]
    private ?CashBox $cashBox = null;

    #[ORM\Column(type: Types::STRING, length: 30, unique: true)]
    private string $folio;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'd/m/Y H:i:s'])]
    protected ?\DateTimeInterface $saleDate = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    private string $subtotal = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    private string $totalTax = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    private string $total = '0.00';

    #[ORM\Column(type: Types::SMALLINT, enumType: SaleStatus::class)]
    private SaleStatus $status = SaleStatus::IN_PROGRESS;

    #[ORM\Column(type: Types::STRING, length: 250, nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\OneToMany(targetEntity: SalePayment::class, mappedBy: 'sale', cascade: ['persist', 'remove'])]
    private Collection $payments;

    private $tips;

    public function addPayment(SalePayment $payment): self
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setSale($this);
        }
        return $this;
    }

    public function removePayment(SalePayment $payment): self
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getSale() === $this) {
                $payment->setSale(null);
            }
        }
        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->saleDate = new \DateTime();
    }


    #[ORM\OneToMany(targetEntity: SaleDetail::class, mappedBy: 'sale', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $details;

    public function __construct()
    {
        // Es vital inicializar esto como ArrayCollection
        $this->details = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->tips = new ArrayCollection();
    }

    public function addTip(Tip $tip): self
    {
        if (!$this->tips->contains($tip)) {
            $this->tips->add($tip);
        }
        return $this;
    }

    public function removeTip(Tip $tip): self
    {
        if ($this->tips->removeElement($tip)) {
            // En tu caso, la entidad Tip no tiene una relación inversa 'sale'
            // (está vinculada a SalePayment), por lo que solo la removemos de la colección.
        }

        return $this;
    }

    public function removeDetail(SaleDetail $detail): self
    {
        if ($this->details->removeElement($detail)) {
            // Establecemos la relación a null si el detalle se remueve
            if ($detail->getSale() === $this) {
                $detail->setSale(null);
            }
        }
        return $this;
    }

    public function getDetails()
    {
        return $this->details;
    }

    public function addDetail(SaleDetail $detail): self
    {
        if (!$this->details->contains($detail)) {
            $this->details->add($detail);
            $detail->setSale($this); // Vincula el detalle con esta venta
        }
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getCashBox(): ?CashBox
    {
        return $this->cashBox;
    }

    public function setCashBox(?CashBox $cashBox): void
    {
        $this->cashBox = $cashBox;
    }

    public function getFolio(): string
    {
        return $this->folio;
    }

    public function setFolio(string $folio): void
    {
        $this->folio = $folio;
    }

    public function getSaleDate(): ?\DateTimeInterface
    {
        return $this->saleDate;
    }

    public function setSaleDate(?\DateTimeInterface $saleDate): void
    {
        $this->saleDate = $saleDate;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getSubtotal(): string
    {
        return $this->subtotal;
    }

    public function setSubtotal(string $subtotal): void
    {
        $this->subtotal = $subtotal;
    }

    public function getTotalTax(): string
    {
        return $this->totalTax;
    }

    public function setTotalTax(string $totalTax): void
    {
        $this->totalTax = $totalTax;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): void
    {
        $this->total = $total;
    }

    public function getStatus(): SaleStatus
    {
        return $this->status;
    }

    public function setStatus(SaleStatus $status): void
    {
        $this->status = $status;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function setCancellationReason(?string $cancellationReason): void
    {
        $this->cancellationReason = $cancellationReason;
    }

    public function getPayments(): Collection
    {
        return $this->payments;
    }

    #[Assert\Callback]
    public function validatePayments(ExecutionContextInterface $context): void
    {
        $totalVenta = (float)$this->subtotal;
        $totalPagos = 0;
        $totalPropinas = 0;

        // Sumar todos los pagos recibidos
        foreach ($this->payments as $payment) {
            $totalPagos += (float)$payment->getAmountReceived();
        }

        // Sumar todas las propinas registradas
        // Asegúrate de tener la propiedad $tips y su getter en esta entidad
        foreach ($this->tips as $tip) {
            $totalPropinas += (float)$tip->getAmount();
        }

        $montoEsperado = $totalVenta + $totalPropinas;

        // Usamos round para evitar problemas de precisión con decimales
        if (round($totalPagos, 2) !== round($montoEsperado, 2)) {
            $context->buildViolation('La suma de los pagos (%payments%) no coincide con el total de la venta + propinas (%total%).')
                ->setParameter('%payments%', number_format($totalPagos, 2))
                ->setParameter('%total%', number_format($montoEsperado, 2))
                ->atPath('payments')
                ->addViolation();
        }
    }


    #[Assert\Callback]
    public function validateTotalWithTips(ExecutionContextInterface $context): void
    {
        $totalVenta = (float)$this->subtotal;
        $totalPagos = 0;
        $totalPropinas = 0;

        foreach ($this->payments as $payment) {
            $totalPagos += (float)$payment->getAmountReceived();
        }

        // Accedemos a las propinas (asumiendo que las manejas en la venta)
        foreach ($this->tips as $tip) {
            $totalPropinas += (float)$tip->getAmount();
        }

        $montoNecesario = $totalVenta + $totalPropinas;

        // Si el cliente pagó menos de lo que suman Venta + Propina
        if (round($totalPagos, 2) < round($montoNecesario, 2)) {
            $context->buildViolation('El total recibido (%pagos%) es insuficiente para cubrir la venta (%venta%) y las propinas (%propinas%).')
                ->setParameter('%pagos%', number_format($totalPagos, 2))
                ->setParameter('%venta%', number_format($totalVenta, 2))
                ->setParameter('%propinas%', number_format($totalPropinas, 2))
                ->atPath('payments')
                ->addViolation();
        }
    }

    public function getTips(): ArrayCollection
    {
        return $this->tips;
    }


}
