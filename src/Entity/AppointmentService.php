<?php

namespace App\Entity;

use App\Repository\AppointmentServiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppointmentServiceRepository::class)]
#[ORM\Table(name: 'tbd_appointment_service')]
#[ORM\UniqueConstraint(name: 'UNIQ_BARBER_SCHEDULE', fields: ['barber', 'branch', 'scheduledDateTime'])]
#[ORM\HasLifecycleCallbacks]
class AppointmentService extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Appointment::class, inversedBy: 'services')]
    #[ORM\JoinColumn(name: 'appointment_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Appointment $appointment = null;

    #[ORM\ManyToOne(targetEntity: MasterProduct::class)]
    #[ORM\JoinColumn(name: 'service_id', referencedColumnName: 'id', nullable: false)]
    private ?MasterProduct $service = null;

    #[ORM\ManyToOne(targetEntity: BarberProfile::class)]
    #[ORM\JoinColumn(name: 'barber_id', referencedColumnName: 'id', nullable: false)]
    private ?BarberProfile $barber = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $scheduledDateTime = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $duration = 30;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $price = '0.00';

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $cartItemId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAppointment(): ?Appointment
    {
        return $this->appointment;
    }

    public function setAppointment(?Appointment $appointment): self
    {
        $this->appointment = $appointment;
        return $this;
    }

    public function getService(): ?MasterProduct
    {
        return $this->service;
    }

    public function setService(?MasterProduct $service): self
    {
        $this->service = $service;
        return $this;
    }

    public function getBarber(): ?BarberProfile
    {
        return $this->barber;
    }

    public function setBarber(?BarberProfile $barber): self
    {
        $this->barber = $barber;
        return $this;
    }

    public function getScheduledDateTime(): ?\DateTimeInterface
    {
        return $this->scheduledDateTime;
    }

    public function setScheduledDateTime(?\DateTimeInterface $scheduledDateTime): self
    {
        $this->scheduledDateTime = $scheduledDateTime;
        return $this;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): self
    {
        $this->duration = $duration;
        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getCartItemId(): ?string
    {
        return $this->cartItemId;
    }

    public function setCartItemId(?string $cartItemId): self
    {
        $this->cartItemId = $cartItemId;
        return $this;
    }
}
