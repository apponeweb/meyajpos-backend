<?php

namespace App\Entity;

use App\Entity\BaseEntity;
use App\Entity\NomenclatorTrait;
use App\Repository\ServiceTypeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ServiceTypeRepository::class)]
#[ORM\Table(name: 'tbn_service_type')]
#[UniqueEntity(fields: ['name'], message: 'Ya existe un tipo de servicio con este nombre.')]
#[ORM\HasLifecycleCallbacks]
class ServiceType extends BaseEntity
{
    use NomenclatorTrait;


    #[ORM\Column(type: Types::BOOLEAN, options: ["default" => false])]
    private bool $isCourtesy = false;

    public function isCourtesy(): bool
    {
        return $this->isCourtesy;
    }

    public function setIsCourtesy(bool $isCourtesy): void
    {
        $this->isCourtesy = $isCourtesy;
    }
}
