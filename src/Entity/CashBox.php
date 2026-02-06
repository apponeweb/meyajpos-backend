<?php

namespace App\Entity;

use App\Entity\BaseEntity;
use App\Entity\NomenclatorTrait;
use App\Repository\CashBoxRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: CashBoxRepository::class)]
#[ORM\Table(name: 'tbd_cash_box')]
#[UniqueEntity(fields: ['name'], message: 'Ya existe una caja con este nombre.')]
#[ORM\HasLifecycleCallbacks]
class CashBox extends BaseEntity
{
    use NomenclatorTrait;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'id', nullable: true)]
    private ?Branch $branch = null;

    #[ORM\Column(type: Types::STRING, length: 250, nullable: true)]
    private string $ticketSerie;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $currentFolio = 0;

    public function __construct()
    {
        $this->currentFolio = 0;
    }

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): void
    {
        $this->branch = $branch;
    }

    public function getTicketSerie()
    {
        return $this->ticketSerie;
    }

    public function setTicketSerie($ticketSerie): void
    {
        $this->ticketSerie = $ticketSerie;
    }

    public function getCurrentFolio(): ?int
    {
        return $this->currentFolio;
    }

    public function setCurrentFolio(?int $currentFolio): void
    {
        $this->currentFolio = $currentFolio;
    }


}
