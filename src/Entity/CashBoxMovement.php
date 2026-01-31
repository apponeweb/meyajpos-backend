<?php

namespace App\Entity;

use App\Enum\CashMovementConcept;
use App\Enum\CashMovementType;
use App\Repository\CashBoxMovementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CashBoxMovementRepository::class)]
#[ORM\Table(name: 'tbd_cash_box_movement')]
#[ORM\HasLifecycleCallbacks]
class CashBoxMovement extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CashBoxSession::class)]
    #[ORM\JoinColumn(name: 'cash_box_session_id', referencedColumnName: 'id', nullable: false)]
    private ?CashBoxSession $cashBoxSession = null;

    #[ORM\Column(type: Types::STRING,length: 50, enumType: CashMovementType::class)]
    private CashMovementType $type;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: CashMovementConcept::class)]
    private CashMovementConcept $concept;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    private ?string $description = null; // Para detalles adicionales del concepto

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    private string $amount= '0.00';

    #[ORM\Column(name: 'cash_change', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $change = '0.00';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $movementDate;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    public function __construct()
    {
        $this->movementDate = new \DateTime();
        $this->change = "0.0";
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCashBoxSession(): ?CashBoxSession
    {
        return $this->cashBoxSession;
    }

    public function setCashBoxSession(?CashBoxSession $session): self
    {
        $this->cashBoxSession = $session;
        return $this;
    }

    public function getType(): CashMovementType
    {
        return $this->type;
    }

    public function setType(CashMovementType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getConcept(): CashMovementConcept
    {
        return $this->concept;
    }

    public function setConcept(CashMovementConcept $concept): self
    {
        $this->concept = $concept;
        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getMovementDate(): \DateTimeInterface
    {
        return $this->movementDate;
    }

    public function setMovementDate(\DateTimeInterface $date): self
    {
        $this->movementDate = $date;
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

    public function getChange(): string
    {
        return $this->change;
    }

    public function setChange(?string $change): self
    {
        $this->change = $change ?? "0.00";
        return $this;
    }


}
