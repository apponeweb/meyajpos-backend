<?php

namespace App\Entity;

use App\Entity\BaseEntity;
use App\Entity\NomenclatorTrait;
use App\Repository\CompanyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\Table(name: 'tbn_company')]
#[UniqueEntity(fields: ['name'], message: 'Ya existe una empresa con este nombre.')]
#[UniqueEntity(fields: ['acronym'], message: 'Ya existe una empresa con estas siglas.')]
#[ORM\HasLifecycleCallbacks]
class Company extends BaseEntity
{
    use NomenclatorTrait;

    #[ORM\Column(type: Types::STRING, length: 20, unique: true)]
    private string $acronym;

    #[ORM\Column(type: Types::STRING, length: 200, nullable: true)]
    private string $legalName;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private string $rfc;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $taxAddress = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $tagline = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $coverImage = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $socialNetworks = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cancellationPolicy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $privacyPolicy = null;

    public function getAcronym(): string
    {
        return $this->acronym;
    }

    public function setAcronym(string $acronym): void
    {
        $this->acronym = $acronym;
    }

    public function getLegalName(): string
    {
        return $this->legalName;
    }

    public function setLegalName(string $legalName): void
    {
        $this->legalName = $legalName;
    }


    public function getRfc(): string
    {
        return $this->rfc;
    }

    public function setRfc(string $rfc): void
    {
        $this->rfc = $rfc;
    }

    public function getTaxAddress(): string
    {
        return $this->taxAddress ?? '';
    }

    public function setTaxAddress(string $taxAddress): void
    {
        $this->taxAddress = $taxAddress;
    }

    public function getPhone()
    {
        return $this->phone ?? '';
    }

    public function setPhone($phone): void
    {
        $this->phone = $phone;
    }

    public function getTagline(): ?string
    {
        return $this->tagline;
    }

    public function setTagline(?string $tagline): void
    {
        $this->tagline = $tagline;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): void
    {
        $this->coverImage = $coverImage;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): void
    {
        $this->logo = $logo;
    }

    public function getSocialNetworks(): ?string
    {
        return $this->socialNetworks;
    }

    public function setSocialNetworks(?string $socialNetworks): void
    {
        $this->socialNetworks = $socialNetworks;
    }

    public function getCancellationPolicy(): ?string
    {
        return $this->cancellationPolicy;
    }

    public function setCancellationPolicy(?string $cancellationPolicy): void
    {
        $this->cancellationPolicy = $cancellationPolicy;
    }

    public function getPrivacyPolicy(): ?string
    {
        return $this->privacyPolicy;
    }

    public function setPrivacyPolicy(?string $privacyPolicy): void
    {
        $this->privacyPolicy = $privacyPolicy;
    }
}
