<?php

namespace App\Entity;

use App\Repository\AppointmentRepository;
use App\Enum\AppointmentStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
#[ORM\Table(name: 'tbd_appointment')]
#[ORM\HasLifecycleCallbacks]
class Appointment extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: false)]
    private ?Customer $customer = null;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'id', nullable: false)]
    private ?Branch $branch = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $totalAmount = '0.00';

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $currency = 'MXN';

    #[ORM\Column(type: Types::SMALLINT, enumType: AppointmentStatus::class)]
    private AppointmentStatus $status = AppointmentStatus::PENDING;

    #[ORM\OneToMany(targetEntity: AppointmentService::class, mappedBy: 'appointment', cascade: ['persist', 'remove'])]
    private Collection $services;

    public function __construct()
    {
        $this->services = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): self
    {
        $this->customer = $customer;
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

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): self
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getStatus(): AppointmentStatus
    {
        return $this->status;
    }

    public function setStatus(AppointmentStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return Collection<int, AppointmentService>
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(AppointmentService $service): self
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
            $service->setAppointment($this);
        }
        return $this;
    }

    public function removeService(AppointmentService $service): self
    {
        if ($this->services->removeElement($service)) {
            if ($service->getAppointment() === $this) {
                $service->setAppointment(null);
            }
        }
        return $this;
    }
}
