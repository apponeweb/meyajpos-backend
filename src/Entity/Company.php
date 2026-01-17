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
#[ORM\HasLifecycleCallbacks]
class Company extends BaseEntity
{
    use NomenclatorTrait;

    #[ORM\Column(type: Types::STRING, length: 20, unique: true)]
    private string $acronym;

    #[ORM\Column(type: Types::STRING, length: 200, nullable: true)]
    private string $legalName;

    #[ORM\Column(type: Types::STRING, length: 200, nullable: true)]
    private string $commercialName;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private string $rfc;

    #[ORM\Column(type: Types::TEXT, length: 20, nullable: true)]
    private string $taxAddress;

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

    public function getCommercialName(): string
    {
        return $this->commercialName;
    }

    public function setCommercialName(string $commercialName): void
    {
        $this->commercialName = $commercialName;
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
        return $this->taxAddress;
    }

    public function setTaxAddress(string $taxAddress): void
    {
        $this->taxAddress = $taxAddress;
    }


}
