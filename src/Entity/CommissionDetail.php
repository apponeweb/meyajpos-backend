<?php

namespace App\Entity;

use App\Enum\ApplicableCommission;
use App\Repository\CommissionDetailsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Query\Expr\Base;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: CommissionDetailsRepository::class)]
#[ORM\Table(name: 'tbd_commission_detail')]
// Definimos el índice único para la dupla
#[ORM\UniqueConstraint(name: 'uniq_commission_service_type', columns: ['commission_id', 'service_type_id'])]
// Opcional: Validador de Symfony para lanzar errores controlados
#[UniqueEntity(
    fields: ['commission', 'serviceType'],
    message: 'Ya existe una configuración de comisión para este tipo de servicio.'
)]
#[ORM\HasLifecycleCallbacks]
class CommissionDetail extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commission::class)]
    #[ORM\JoinColumn(name: 'commission_id', referencedColumnName: 'id', nullable: false)]
    private ?Commission $commission = null;


    #[ORM\Column(type: Types::SMALLINT, enumType: ApplicableCommission::class)]
    private ApplicableCommission $applicableCommission = ApplicableCommission::ALL;

    #[ORM\ManyToOne(targetEntity: ServiceType::class)]
    #[ORM\JoinColumn(name: 'service_type_id', referencedColumnName: 'id', nullable: false)]
    private ?ServiceType $serviceType = null;


    #[ORM\Column(type: Types::DECIMAL, precision: 9, scale: 4)]
    private string $percentage;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getCommission(): ?Commission
    {
        return $this->commission;
    }

    public function setCommission(?Commission $commission): void
    {
        $this->commission = $commission;
    }

    public function getApplicableCommission(): ApplicableCommission
    {
        return $this->applicableCommission;
    }

    public function setApplicableCommission(ApplicableCommission $applicableCommission): void
    {
        $this->applicableCommission = $applicableCommission;
    }

    public function getServiceType(): ?ServiceType
    {
        return $this->serviceType;
    }

    public function setServiceType(?ServiceType $serviceType): void
    {
        $this->serviceType = $serviceType;
    }

    public function getPercentage(): string
    {
        return $this->percentage;
    }

    public function setPercentage(string $percentage): void
    {
        $this->percentage = $percentage;
    }


}
