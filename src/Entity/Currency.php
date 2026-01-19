<?php

namespace App\Entity;

use App\Entity\BaseEntity;
use App\Entity\NomenclatorTrait;
use App\Repository\CurrencyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: CurrencyRepository::class)]
#[ORM\Table(name: 'tbn_currency')]
#[UniqueEntity(fields: ['code'], message: 'Ya existe una moneda con este código.')]
#[UniqueEntity(fields: ['symbol'], message: 'Ya existe una moneda con estas siglas.')]
class Currency extends BaseEntity
{
    use NomenclatorTrait;

    #[ORM\Column(type: Types::STRING, length: 3, unique: true)]
    private ?string $code = null; // Ej: MXN, USD, EUR

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    private ?string $symbol = null; // Ej: $, €, £

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 6)]
    private ?string $exchangeRate = '1.000000';

    public function __construct()
    {
        $this->exchangeRate = '1.000000';
        $this->code = 'MXN';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = strtoupper($code);
        return $this;
    }

    public function getSymbol(): ?string
    {
        return $this->symbol;
    }

    public function setSymbol(?string $symbol): self
    {
        $this->symbol = $symbol;
        return $this;
    }

    public function getExchangeRate(): ?string
    {
        return $this->exchangeRate;
    }

    public function setExchangeRate(string $exchangeRate): self
    {
        $this->exchangeRate = $exchangeRate;
        return $this;
    }
}
