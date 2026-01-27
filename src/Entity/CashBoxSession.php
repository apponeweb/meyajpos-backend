<?php

namespace App\Entity;

use App\Enum\CashBoxSessionStatus;
use App\Repository\CashBoxSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CashBoxSessionRepository::class)]
#[ORM\Table(name: 'tbd_cash_box_session')]
#[ORM\HasLifecycleCallbacks]
class CashBoxSession extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'id', nullable: false)]
    private ?Branch $branch = null;

    #[ORM\ManyToOne(targetEntity: CashBox::class)]
    #[ORM\JoinColumn(name: 'cash_box_id', referencedColumnName: 'id', nullable: false)]
    private ?CashBox $cashBox = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null; // Cajero responsable

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $openingDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private ?string $initialAmount = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'closing_user_id', referencedColumnName: 'id', nullable: true)]
    private ?User $closingUser = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $closingDate = null;

    /**
     * Al indicar enumType, Doctrine mapea el int de la BD al objeto Enum de PHP
     */
    #[ORM\Column(type: Types::SMALLINT, enumType: CashBoxSessionStatus::class)]
    private CashBoxSessionStatus $status = CashBoxSessionStatus::OPEN;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): void
    {
        $this->branch = $branch;
    }

    public function getCashBox(): ?CashBox
    {
        return $this->cashBox;
    }

    public function setCashBox(?CashBox $cashBox): void
    {
        $this->cashBox = $cashBox;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }


    public function getOpeningDate(): ?\DateTimeInterface
    {
        return $this->openingDate;
    }

    public function setOpeningDate(\DateTimeInterface $openingDate): self
    {
        $this->openingDate = $openingDate;
        return $this;
    }

    public function getInitialAmount(): ?string
    {
        return $this->initialAmount;
    }

    public function setInitialAmount(string $initialAmount): self
    {
        $this->initialAmount = $initialAmount;
        return $this;
    }

    public function getClosingUser(): ?User
    {
        return $this->closingUser;
    }

    public function setClosingUser(?User $closingUser): self
    {
        $this->closingUser = $closingUser;
        return $this;
    }

    public function getClosingDate(): ?\DateTimeInterface
    {
        return $this->closingDate;
    }

    public function setClosingDate(?\DateTimeInterface $closingDate): self
    {
        $this->closingDate = $closingDate;
        return $this;
    }

    public function getStatus(): CashBoxSessionStatus
    {
        return $this->status;
    }

    public function setStatus(CashBoxSessionStatus $status): self
    {
        $this->status = $status;
        return $this;
    }
}
