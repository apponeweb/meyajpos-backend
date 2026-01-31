<?php

namespace App\Entity;

use App\Repository\XReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: XReportRepository::class)]
#[ORM\Table(name: 'tbd_x_report')]
#[ORM\HasLifecycleCallbacks]
class XReport extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CashBoxSession::class)]
    #[ORM\JoinColumn(name: 'cash_session_id', referencedColumnName: 'id', nullable: false)]
    private ?CashBoxSession $cashSession = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $reportNumber;

    /**
     * Usamos DATETIME_MUTABLE para asegurar compatibilidad con fecha y hora.
     */
    #[ORM\Column(name: 'x_report_date', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $xReportDate;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $systemTotal;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $declaredTotal;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $difference;

    #[ORM\Column(type: Types::STRING, length: 250, nullable: true)]
    private ?string $observations = null;

    #[ORM\OneToMany(targetEntity: XReportDetail::class, mappedBy: 'xReport', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $details;

    public function __construct()
    {
        // Esto inicializa la fecha y hora exacta del servidor al crear el objeto
        $this->xReportDate = new \DateTime();
        $this->systemTotal = '0.00';
        $this->declaredTotal = '0.00';
        $this->difference = '0.00';
        $this->details = new ArrayCollection();
    }


    // --- Getters & Setters ---

    /**
     * @return Collection<int, XReportDetail>
     */
    public function getDetails(): Collection
    {
        return $this->details;
    }

    public function addDetail(XReportDetail $detail): self
    {
        if (!$this->details->contains($detail)) {
            $this->details->add($detail);
            $detail->setXReport($this);
        }
        return $this;
    }

    public function removeDetail(XReportDetail $detail): self
    {
        if ($this->details->removeElement($detail)) {
            // set the owning side to null (unless already changed)
            if ($detail->getXReport() === $this) {
                $detail->setXReport(null);
            }
        }
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCashSession(): ?CashBoxSession
    {
        return $this->cashSession;
    }

    public function setCashSession(?CashBoxSession $cashSession): self
    {
        $this->cashSession = $cashSession;
        return $this;
    }

    public function getReportNumber(): int
    {
        return $this->reportNumber;
    }

    public function setReportNumber(int $reportNumber): self
    {
        $this->reportNumber = $reportNumber;
        return $this;
    }

    public function getXReportDate(): \DateTimeInterface
    {
        return $this->xReportDate;
    }

    public function setXReportDate(\DateTimeInterface $xReportDate): self
    {
        $this->xReportDate = $xReportDate;
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

    public function getSystemTotal(): string
    {
        return $this->systemTotal;
    }

    public function setSystemTotal(string $systemTotal): self
    {
        $this->systemTotal = $systemTotal;
        $this->updateDifference();
        return $this;
    }

    public function getDeclaredTotal(): string
    {
        return $this->declaredTotal;
    }

    public function setDeclaredTotal(string $declaredTotal): self
    {
        $this->declaredTotal = $declaredTotal;
        $this->updateDifference();
        return $this;
    }

    public function getDifference(): string
    {
        return $this->difference;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): self
    {
        $this->observations = $observations;
        return $this;
    }

    /**
     * Mantiene la lógica de la diferencia siempre actualizada
     */
    private function updateDifference(): void
    {
        $this->difference = bcsub($this->systemTotal, $this->declaredTotal, 2);
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function calculateTotalDifference(): void
    {
        // Diferencia = Sistema - Declarado
        if (function_exists('bcsub')) {
            $this->difference = bcsub($this->systemTotal, $this->declaredTotal, 2);
        } else {
            $this->difference = (string)(floatval($this->systemTotal) - floatval($this->declaredTotal));
        }
    }

    #[ORM\PrePersist]
    public function syncTotalsFromDetails(): void
    {
        $sys = '0.00';
        $dec = '0.00';

        foreach ($this->details as $detail) {
            $sys = bcadd($sys, $detail->getSystemAmount(), 2);
            $dec = bcadd($dec, $detail->getDeclaredAmount(), 2);
        }

        $this->systemTotal = $sys;
        $this->declaredTotal = $dec;

        // Calculamos la diferencia final
        $this->difference = bcsub($this->systemTotal, $this->declaredTotal, 2);
    }
}
