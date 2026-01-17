<?php

namespace App\Entity;

use App\Entity\BaseEntity;
use App\Entity\NomenclatorTrait;
use App\Repository\ServiceTypeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

#[ORM\Entity(repositoryClass: ServiceTypeRepository::class)]
#[ORM\Table(name: 'tbn_change_type')]
#[UniqueEntity(fields: ['name'], message: 'Ya existe un tipo de cambio con este nombre.')]
#[ORM\HasLifecycleCallbacks]
class ChangeType extends BaseEntity
{
    use NomenclatorTrait;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(name: 'currency_id', referencedColumnName: 'id', nullable: true)]
    private ?Currency $currencyOrigin = null;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(name: 'currency_id', referencedColumnName: 'id', nullable: true)]
    private ?Currency $currencyDestination = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'd/m/Y H:i:s'])]
    private ?\DateTimeInterface $taxDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $changeType = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    private string $source;

    public function getCurrencyOrigin(): ?Currency
    {
        return $this->currencyOrigin;
    }

    public function setCurrencyOrigin(?Currency $currencyOrigin): void
    {
        $this->currencyOrigin = $currencyOrigin;
    }

    public function getCurrencyDestination(): ?Currency
    {
        return $this->currencyDestination;
    }

    public function setCurrencyDestination(?Currency $currencyDestination): void
    {
        $this->currencyDestination = $currencyDestination;
    }

    public function getTaxDate(): ?\DateTimeInterface
    {
        return $this->taxDate;
    }

    public function setTaxDate(?\DateTimeInterface $taxDate): void
    {
        $this->taxDate = $taxDate;
    }

    public function getChangeType(): ?string
    {
        return $this->changeType;
    }

    public function setChangeType(?string $changeType): void
    {
        $this->changeType = $changeType;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
    }


}
