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

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $rating = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $reviewCount = null;

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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): void
    {
        $this->image = $image;
    }

    public function getRating(): ?float
    {
        return $this->rating;
    }

    public function setRating(?float $rating): void
    {
        $this->rating = $rating;
    }

    public function getReviewCount(): ?int
    {
        return $this->reviewCount;
    }

    public function setReviewCount(?int $reviewCount): void
    {
        $this->reviewCount = $reviewCount;
    }

}
