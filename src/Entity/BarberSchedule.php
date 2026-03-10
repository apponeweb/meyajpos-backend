<?php

namespace App\Entity;

use App\Repository\BarberScheduleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BarberScheduleRepository::class)]
#[ORM\Table(name: 'tbd_barber_schedules')]
#[ORM\HasLifecycleCallbacks]
class BarberSchedule extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'barber_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $barber = null;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Branch $branch = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $dayOfWeek;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private \DateTimeInterface $openTime;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private \DateTimeInterface $closeTime;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $validFrom;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validTo = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 30])]
    private int $slotMinutes = 30;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 60])]
    private int $turnDuration = 60;

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

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): self
    {
        $this->branch = $branch;
        return $this;
    }

    public function getDayOfWeek(): int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): self
    {
        $this->dayOfWeek = $dayOfWeek;
        return $this;
    }

    public function getOpenTime(): \DateTimeInterface
    {
        return $this->openTime;
    }

    public function setOpenTime(\DateTimeInterface $openTime): self
    {
        $this->openTime = $openTime;
        return $this;
    }

    public function getCloseTime(): \DateTimeInterface
    {
        return $this->closeTime;
    }

    public function setCloseTime(\DateTimeInterface $closeTime): self
    {
        $this->closeTime = $closeTime;
        return $this;
    }

    public function getValidFrom(): \DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(\DateTimeInterface $validFrom): self
    {
        $this->validFrom = $validFrom;
        return $this;
    }

    public function getValidTo(): ?\DateTimeInterface
    {
        return $this->validTo;
    }

    public function setValidTo(?\DateTimeInterface $validTo): self
    {
        $this->validTo = $validTo;
        return $this;
    }

    public function getSlotMinutes(): int
    {
        return $this->slotMinutes;
    }

    public function setSlotMinutes(int $slotMinutes): self
    {
        $this->slotMinutes = $slotMinutes;
        return $this;
    }

    public function getTurnDuration(): int
    {
        return $this->turnDuration;
    }

    public function setTurnDuration(int $turnDuration): self
    {
        $this->turnDuration = $turnDuration;
        return $this;
    }
}
