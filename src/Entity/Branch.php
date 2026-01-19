<?php

namespace App\Entity;

use App\Entity\BaseEntity;
use App\Entity\NomenclatorTrait;
use App\Repository\BranchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: BranchRepository::class)]
#[ORM\Table(name: 'tbn_branch')]
#[UniqueEntity(fields: ['name'], message: 'Ya existe una sucursal con este nombre.')]
#[UniqueEntity(fields: ['acronym'], message: 'Ya existe una sucursal con estas siglas.')]
#[ORM\HasLifecycleCallbacks]
class Branch extends BaseEntity
{
    use NomenclatorTrait;


    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: true)]
    private ?Company $company = null;

    #[ORM\Column(type: Types::STRING, length: 20, unique: true)]
    private string $acronym;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private string $address;

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    public function getAcronym(): string
    {
        return $this->acronym;
    }

    public function setAcronym(string $acronym): void
    {
        $this->acronym = $acronym;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): void
    {
        $this->company = $company;
    }

}
