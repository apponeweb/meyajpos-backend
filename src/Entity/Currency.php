<?php

namespace App\Entity;

use App\Entity\BaseEntity;
use App\Entity\NomenclatorTrait;
use App\Repository\BranchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: BranchRepository::class)]
#[ORM\Table(name: 'tbn_currency')]
#[UniqueEntity(fields: ['name'], message: 'Ya existe una moneda con este nombre.')]
#[ORM\HasLifecycleCallbacks]
class Currency extends BaseEntity
{
    use NomenclatorTrait;

    #[ORM\Column(type: Types::STRING, length: 20, unique: true)]
    private string $key;
    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $symbol;

    #[ORM\Column(type: Types::INTEGER)]
    private int $decimals;

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): void
    {
        $this->symbol = $symbol;
    }

    public function getDecimals(): int
    {
        return $this->decimals;
    }

    public function setDecimals(int $decimals): void
    {
        $this->decimals = $decimals;
    }


}
