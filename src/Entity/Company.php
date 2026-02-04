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
    private string $phone;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private string $rfc;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $taxAddress = null;

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

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }


}
