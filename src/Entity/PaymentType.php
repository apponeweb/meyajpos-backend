<?php

namespace App\Entity;

use App\Entity\BaseEntity;
use App\Entity\NomenclatorTrait;
use App\Repository\PaymentTypeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: PaymentTypeRepository::class)]
#[ORM\Table(name: 'tbn_payment_type')]
#[UniqueEntity(fields: ['name'], message: 'Ya existe un tipo de pago con este nombre.')]
#[ORM\HasLifecycleCallbacks]
class PaymentType extends BaseEntity
{
    use NomenclatorTrait;

    #[ORM\Column(type: Types::BOOLEAN, options: ["default" => false])]
    private bool $referenceRequired = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ["default" => false])]
    private bool $isCash = false;

    public function isReferenceRequired(): bool
    {
        return $this->referenceRequired;
    }

    public function setReferenceRequired(bool $referenceRequired): void
    {
        $this->referenceRequired = $referenceRequired;
    }

    public function isCash(): bool
    {
        return $this->isCash;
    }

    public function setIsCash(bool $isCash): void
    {
        $this->isCash = $isCash;
    }



}
