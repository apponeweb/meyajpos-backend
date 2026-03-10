<?php

namespace App\Entity;

use App\Repository\BarberSpecialtyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BarberSpecialtyRepository::class)]
#[ORM\Table(name: 'tbr_barber_specialty')]
#[ORM\UniqueConstraint(name: 'uniq_barber_specialty', columns: ['barber_user_id', 'specialty_id'])]
#[ORM\HasLifecycleCallbacks]
class BarberSpecialty extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'barber_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $barber = null;

    #[ORM\ManyToOne(targetEntity: Specialty::class)]
    #[ORM\JoinColumn(name: 'specialty_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Specialty $specialty = null;

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

    public function getSpecialty(): ?Specialty
    {
        return $this->specialty;
    }

    public function setSpecialty(?Specialty $specialty): self
    {
        $this->specialty = $specialty;
        return $this;
    }
}
