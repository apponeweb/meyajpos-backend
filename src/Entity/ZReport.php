<?php

namespace App\Entity;

use App\Repository\ZReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: ZReportRepository::class)]
#[ORM\Table(name: 'tbd_z_report')]
#[ORM\HasLifecycleCallbacks]
class ZReport extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: CashBoxSession::class)]
    #[ORM\JoinColumn(name: 'cash_box_session_id', referencedColumnName: 'id', nullable: false)]
    private ?CashBoxSession $cashBoxSession = null;

    #[ORM\Column(length: 30)]
    private ?string $folioZ = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $closingDate = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $totalSales = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $totalCancellations = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $totalTips = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $totalCashIn = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $totalCashOut = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $expectedCash = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $declaredCash = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $cashDifference = '0.00';

    #[ORM\OneToMany(targetEntity: ZReportDetail::class, mappedBy: 'zReport', cascade: ['persist', 'remove'])]
    private Collection $details;



    /**
     * @return Collection<int, ZReportDetail>
     */
    public function getDetails(): Collection
    {
        return $this->details;
    }

    public function addDetail(ZReportDetail $detail): self
    {
        if (!$this->details->contains($detail)) {
            $this->details->add($detail);
            $detail->setZReport($this);
        }
        return $this;
    }

    public function removeDetail(ZReportDetail $detail): self
    {
        if ($this->details->removeElement($detail)) {
            // set the owning side to null (unless already changed)
            if ($detail->getZReport() === $this) {
                $detail->getZReport(null);
            }
        }
        return $this;
    }

    public function __construct()
    {
        $this->closingDate = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getCashBoxSession(): ?CashBoxSession
    {
        return $this->cashBoxSession;
    }

    public function setCashBoxSession(?CashBoxSession $cashBoxSession): void
    {
        $this->cashBoxSession = $cashBoxSession;
    }

    public function getFolioZ(): ?string
    {
        return $this->folioZ;
    }

    public function setFolioZ(?string $folioZ): void
    {
        $this->folioZ = $folioZ;
    }

    public function getClosingDate(): ?\DateTimeInterface
    {
        return $this->closingDate;
    }

    public function setClosingDate(?\DateTimeInterface $closingDate): void
    {
        $this->closingDate = $closingDate;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getTotalSales(): string
    {
        return $this->totalSales;
    }

    public function setTotalSales(string $totalSales): void
    {
        $this->totalSales = $totalSales;
    }

    public function getTotalCancellations(): string
    {
        return $this->totalCancellations;
    }

    public function setTotalCancellations(string $totalCancellations): void
    {
        $this->totalCancellations = $totalCancellations;
    }

    public function getTotalTips(): string
    {
        return $this->totalTips;
    }

    public function setTotalTips(string $totalTips): void
    {
        $this->totalTips = $totalTips;
    }

    public function getTotalCashIn(): string
    {
        return $this->totalCashIn;
    }

    public function setTotalCashIn(string $totalCashIn): void
    {
        $this->totalCashIn = $totalCashIn;
    }

    public function getTotalCashOut(): string
    {
        return $this->totalCashOut;
    }

    public function setTotalCashOut(string $totalCashOut): void
    {
        $this->totalCashOut = $totalCashOut;
    }

    public function getExpectedCash(): string
    {
        return $this->expectedCash;
    }

    public function setExpectedCash(string $expectedCash): void
    {
        $this->expectedCash = $expectedCash;
    }

    public function getDeclaredCash(): string
    {
        return $this->declaredCash;
    }

    public function setDeclaredCash(string $declaredCash): void
    {
        $this->declaredCash = $declaredCash;
    }

    public function getCashDifference(): string
    {
        return $this->cashDifference;
    }

    public function setCashDifference(string $cashDifference): void
    {
        $this->cashDifference = $cashDifference;
    }

}
