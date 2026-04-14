<?php

namespace App\License\Entity;

use App\Repository\BaseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'lic_license_type')]
class LicLicenseType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['license:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['license:read'])]
    private string $name = '';

    #[ORM\Column(length: 20, unique: true)]
    #[Groups(['license:read'])]
    private string $code = '';

    #[ORM\Column]
    #[Groups(['license:read'])]
    private int $maxActivations = 1;

    #[ORM\Column]
    #[Groups(['license:read'])]
    private int $maxBranches = 1;

    #[ORM\Column]
    #[Groups(['license:read'])]
    private int $maxBarbers = 5;

    #[ORM\Column]
    #[Groups(['license:read'])]
    private int $durationDays = 30;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['license:read'])]
    private bool $isActive = true;

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getCode(): string { return $this->code; }
    public function setCode(string $code): self { $this->code = $code; return $this; }

    public function getMaxActivations(): int { return $this->maxActivations; }
    public function setMaxActivations(int $maxActivations): self { $this->maxActivations = $maxActivations; return $this; }

    public function getMaxBranches(): int { return $this->maxBranches; }
    public function setMaxBranches(int $maxBranches): self { $this->maxBranches = $maxBranches; return $this; }

    public function getMaxBarbers(): int { return $this->maxBarbers; }
    public function setMaxBarbers(int $maxBarbers): self { $this->maxBarbers = $maxBarbers; return $this; }

    public function getDurationDays(): int { return $this->durationDays; }
    public function setDurationDays(int $durationDays): self { $this->durationDays = $durationDays; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
}
