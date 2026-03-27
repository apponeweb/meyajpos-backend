<?php

namespace App\Entity;

use App\Repository\BarberProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BarberProfileRepository::class)]
#[ORM\Table(name: 'tbd_barber_profile')]
#[ORM\HasLifecycleCallbacks]
class BarberProfile extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'profile', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'barber_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $photoUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, options: ['default' => 0])]
    private ?string $avgRating = '0.00';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $ratingCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 30])]
    private int $slotMinutes = 30;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $classification = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $experience = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPhotoUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function setPhotoUrl(?string $photoUrl): self
    {
        $this->photoUrl = $photoUrl;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    public function getAvgRating(): ?string
    {
        return $this->avgRating;
    }

    public function setAvgRating(?string $avgRating): self
    {
        $this->avgRating = $avgRating;
        return $this;
    }

    public function getRatingCount(): int
    {
        return $this->ratingCount;
    }

    public function setRatingCount(int $ratingCount): self
    {
        $this->ratingCount = $ratingCount;
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

    public function getClassification(): ?string
    {
        return $this->classification;
    }

    public function setClassification(?string $classification): self
    {
        $this->classification = $classification;
        return $this;
    }

    public function getExperience(): ?string
    {
        return $this->experience;
    }

    public function setExperience(?string $experience): self
    {
        $this->experience = $experience;
        return $this;
    }
}
