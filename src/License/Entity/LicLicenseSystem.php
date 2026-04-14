<?php

namespace App\License\Entity;

use App\License\Repository\LicLicenseSystemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LicLicenseSystemRepository::class)]
#[ORM\Table(name: 'lic_license_system')]
#[ORM\UniqueConstraint(name: 'UNIQ_LICENSE_SYSTEM', fields: ['license', 'system'])]
class LicLicenseSystem
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LicLicense::class, inversedBy: 'licenseSystems')]
    #[ORM\JoinColumn(name: 'license_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private LicLicense $license;

    #[ORM\ManyToOne(targetEntity: LicSystem::class)]
    #[ORM\JoinColumn(name: 'system_id', referencedColumnName: 'id', nullable: false)]
    private LicSystem $system;

    public function getId(): ?int { return $this->id; }

    public function getLicense(): LicLicense { return $this->license; }
    public function setLicense(LicLicense $license): void { $this->license = $license; }

    public function getSystem(): LicSystem { return $this->system; }
    public function setSystem(LicSystem $system): void { $this->system = $system; }
}
