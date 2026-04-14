<?php

namespace App\License\Entity;

use App\Entity\Company;
use App\Entity\User;
use App\Entity\BaseEntity;
use App\License\Repository\LicLicenseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LicLicenseRepository::class)]
#[ORM\Table(name: 'lic_license')]
#[ORM\HasLifecycleCallbacks]
class LicLicense extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false)]
    private Company $company;

    #[ORM\Column(type: Types::INTEGER)]
    private int $maxBranches = 1;

    #[ORM\Column(type: Types::INTEGER)]
    private int $maxBarbers = 1;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $startDate;

    #[ORM\Column(type: Types::INTEGER)]
    private int $durationDays = 30;

    #[ORM\Column(length: 50, unique: true, nullable: true)]
    private ?string $licenseKey = null;

    #[ORM\ManyToOne(targetEntity: LicLicenseType::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?LicLicenseType $type = null;

    #[ORM\Column]
    private ?int $maxActivations = 1;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $hardwareIds = [];

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $activatedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * @var Collection<int, LicLicenseSystem>
     */
    #[ORM\OneToMany(targetEntity: LicLicenseSystem::class, mappedBy: 'license', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $licenseSystems;

    public function __construct()
    {
        $this->licenseSystems = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        parent::onPrePersist();
        $this->computeExpiresAt();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        parent::onPreUpdate();
        $this->computeExpiresAt();
    }

    private function computeExpiresAt(): void
    {
        // La fecha de inicio para el cálculo esactivatedAt si existe, si no startDate
        $referenceDate = $this->activatedAt ?? ($this->startDate ?? null);

        if ($referenceDate && isset($this->durationDays)) {
            $expires = clone $referenceDate;
            $expires->modify('+' . $this->durationDays . ' days');
            $this->expiresAt = $expires;
        }
    }

    public function isExpired(): bool
    {
        if (!$this->expiresAt) {
            return true;
        }
        return $this->expiresAt < new \DateTime('today');
    }

    public function getSystemCodes(): array
    {
        return array_values(array_map(
            fn(LicLicenseSystem $ls) => $ls->getSystem()->getCode(),
            $this->licenseSystems->toArray()
        ));
    }

    // Getters & Setters

    public function getId(): ?int { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): void { $this->user = $user; }

    public function getCompany(): Company { return $this->company; }
    public function setCompany(Company $company): void { $this->company = $company; }

    public function getMaxBranches(): int { return $this->maxBranches; }
    public function setMaxBranches(int $maxBranches): void { $this->maxBranches = $maxBranches; }

    public function getMaxBarbers(): int { return $this->maxBarbers; }
    public function setMaxBarbers(int $maxBarbers): void { $this->maxBarbers = $maxBarbers; }

    public function getStartDate(): \DateTimeInterface { return $this->startDate; }
    public function setStartDate(\DateTimeInterface $startDate): void
    {
        $this->startDate = $startDate;
        $this->computeExpiresAt();
    }

    public function getDurationDays(): int { return $this->durationDays; }
    public function setDurationDays(int $durationDays): void
    {
        $this->durationDays = $durationDays;
        $this->computeExpiresAt();
    }

    public function getExpiresAt(): \DateTimeInterface { return $this->expiresAt; }
    public function setExpiresAt(\DateTimeInterface $expiresAt): void { $this->expiresAt = $expiresAt; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): void { $this->notes = $notes; }

    public function getLicenseSystems(): Collection { return $this->licenseSystems; }

    public function addLicenseSystem(LicLicenseSystem $licenseSystem): void
    {
        if (!$this->licenseSystems->contains($licenseSystem)) {
            $this->licenseSystems->add($licenseSystem);
            $licenseSystem->setLicense($this);
        }
    }

    public function removeLicenseSystem(LicLicenseSystem $licenseSystem): void
    {
        $this->licenseSystems->removeElement($licenseSystem);
    }

    public function clearLicenseSystems(): void
    {
        $this->licenseSystems->clear();
    }

    public function getLicenseKey(): ?string
    {
        return $this->licenseKey;
    }

    public function setLicenseKey(?string $licenseKey): self
    {
        $this->licenseKey = $licenseKey;
        return $this;
    }

    public function getType(): ?LicLicenseType
    {
        return $this->type;
    }

    public function setType(?LicLicenseType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getMaxActivations(): ?int
    {
        return $this->maxActivations;
    }

    public function setMaxActivations(?int $maxActivations): self
    {
        $this->maxActivations = $maxActivations;
        return $this;
    }

    public function getHardwareIds(): ?array
    {
        return $this->hardwareIds;
    }

    public function setHardwareIds(?array $hardwareIds): self
    {
        $this->hardwareIds = $hardwareIds;
        return $this;
    }

    public function addHardwareId(string $id): self
    {
        if ($this->hardwareIds === null) {
            $this->hardwareIds = [];
        }
        if (!in_array($id, $this->hardwareIds)) {
            $this->hardwareIds[] = $id;
        }
        return $this;
    }

    public function getActivatedAt(): ?\DateTimeInterface { return $this->activatedAt; }

    public function setActivatedAt(?\DateTimeInterface $activatedAt): void
    {
        $this->activatedAt = $activatedAt;
        $this->computeExpiresAt();
    }
}
