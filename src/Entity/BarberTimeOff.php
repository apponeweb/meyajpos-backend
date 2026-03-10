<?php

namespace App\Entity;

use App\Repository\BarberTimeOffRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Branch;

#[ORM\Entity(repositoryClass: BarberTimeOffRepository::class)]
#[ORM\Table(name: 'tbd_barber_time_off')]
#[ORM\HasLifecycleCallbacks]
class BarberTimeOff extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'barber_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $barber = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $startAtLocal;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $endAtLocal;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Branch $branch = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBarber(): ?User
    {
        return $this->barber;
    }

    public function setBarber(?User $barber): self
    {
        $this->barber = $barber;
        return $this;
    }

    public function getStartAtLocal(): \DateTimeInterface
    {
        return $this->startAtLocal;
    }

    public function setStartAtLocal(\DateTimeInterface $startAtLocal): self
    {
        $this->startAtLocal = $startAtLocal;
        return $this;
    }

    public function getEndAtLocal(): \DateTimeInterface
    {
        return $this->endAtLocal;
    }

    public function setEndAtLocal(\DateTimeInterface $endAtLocal): self
    {
        $this->endAtLocal = $endAtLocal;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;
        return $this;
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
}
