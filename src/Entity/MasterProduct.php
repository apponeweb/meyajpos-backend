<?php

namespace App\Entity;

use App\Repository\MasterProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MasterProductRepository::class)]
#[ORM\Table(name: 'tbd_master_product')]
#[UniqueEntity(fields: ['name'], message: 'Ya existe un producto maestro con este nombre.')]
#[UniqueEntity(fields: ['sku'], message: 'Ya existe un sku con este nombre.')]
#[UniqueEntity(fields: ['barcode'], message: 'Ya existe un barcode con este nombre.')]
#[ORM\HasLifecycleCallbacks]
class MasterProduct extends BaseEntity
{
    // 1. Esto genera ID, NAME y DESCRIPTION primero
    use NomenclatorTrait;

    // 2. Luego los campos específicos de la entidad
    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    private string $sku;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $barcode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private string $price;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ["default" => "0.00"])]
    private string $vatRate = '0.00'; // este seria el iva

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ["default" => true])]
    private bool $isInventoriable = true;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private string $uom;

    #[ORM\ManyToOne(targetEntity: ServiceType::class)]
    #[ORM\JoinColumn(name: 'service_type_id', referencedColumnName: 'id', nullable: true)]
    private ?ServiceType $serviceType = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $image = null;

    public function getSku(): string
    {
        return $this->sku;
    }

    public function setSku(string $sku): void
    {
        $this->sku = $sku;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): void
    {
        $this->barcode = $barcode;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): void
    {
        $this->price = $price;
    }

    public function isInventoriable(): bool
    {
        return $this->isInventoriable;
    }

    public function setIsInventoriable(bool $isInventoriable): void
    {
        $this->isInventoriable = $isInventoriable;
    }

    public function getUom(): string
    {
        return $this->uom;
    }

    public function setUom(string $uom): void
    {
        $this->uom = $uom;
    }

    public function getServiceType(): ?ServiceType
    {
        return $this->serviceType;
    }

    public function setServiceType(?ServiceType $serviceType): void
    {
        $this->serviceType = $serviceType;
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function setVatRate(string $vatRate): void
    {
        $this->vatRate = $vatRate;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): void
    {
        $this->image = $image;
    }
}
