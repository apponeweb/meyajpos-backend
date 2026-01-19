<?php

namespace App\Entity;

use App\Entity\BaseEntity;
use App\Entity\NomenclatorTrait;
use App\Repository\CommissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: CommissionRepository::class)]
#[ORM\Table(name: 'tbn_commission')]
#[UniqueEntity(fields: ['name'], message: 'Ya existe una comisión con este nombre.')]
#[ORM\HasLifecycleCallbacks]
class Commission extends BaseEntity
{
    use NomenclatorTrait;



}
