<?php

namespace App\Entity;

use App\Repository\AppointmentStatusConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppointmentStatusConfigRepository::class)]
#[ORM\Table(name: 'tbd_appointment_status_config')]
#[ORM\HasLifecycleCallbacks]
class AppointmentStatusConfig extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER, unique: true)]
    private int $status;

    #[ORM\Column(length: 100)]
    private string $label;

    #[ORM\Column(length: 7)]
    private string $color;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): self
    {
        $this->color = $color;
        return $this;
    }
}
