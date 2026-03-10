<?php

namespace App\Entity;

use App\Repository\BarberServiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BarberServiceRepository::class)]
#[ORM\Table(name: 'tbr_barber_service')]
#[ORM\UniqueConstraint(name: 'uniq_barber_product', columns: ['barber_user_id', 'product_id'])]
#[ORM\HasLifecycleCallbacks]
class BarberService extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'barber_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $barber = null;

    #[ORM\ManyToOne(targetEntity: MasterProduct::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false)]
    private ?MasterProduct $product = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $durationOverrideMinutes = null;

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

    public function getProduct(): ?MasterProduct
    {
        return $this->product;
    }

    public function setProduct(?MasterProduct $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getDurationOverrideMinutes(): ?int
    {
        return $this->durationOverrideMinutes;
    }

    public function setDurationOverrideMinutes(?int $durationOverrideMinutes): self
    {
        $this->durationOverrideMinutes = $durationOverrideMinutes;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }
}
