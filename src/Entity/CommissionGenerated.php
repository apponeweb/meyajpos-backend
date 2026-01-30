<?php

namespace App\Entity;

use App\Enum\CommissionPaymentStatus;
use App\Repository\CommissionGeneratedRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommissionGeneratedRepository::class)]
#[ORM\Table(name: 'tbd_commission_generated')]
#[ORM\HasLifecycleCallbacks]
class CommissionGenerated extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'commission_generated_id', type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SaleDetail::class)]
    #[ORM\JoinColumn(name: 'sale_detail_id', referencedColumnName: 'id', nullable: false)]
    private ?SaleDetail $saleDetail = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    private ?string $baseCommission = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 9, scale: 4)]
    private ?string $percentage = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private ?string $commissionAmount = null;

    #[ORM\Column(
        type: Types::SMALLINT,
        enumType: CommissionPaymentStatus::class,
        options: ["default" => CommissionPaymentStatus::PAID->value]
    )]
    private CommissionPaymentStatus $paymentStatus = CommissionPaymentStatus::PAID;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaleDetail(): ?SaleDetail
    {
        return $this->saleDetail;
    }

    public function setSaleDetail(?SaleDetail $saleDetail): self
    {
        $this->saleDetail = $saleDetail;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getBaseCommission(): ?string
    {
        return $this->baseCommission;
    }

    public function setBaseCommission(string $baseCommission): self
    {
        $this->baseCommission = $baseCommission;
        return $this;
    }

    public function getPercentage(): ?string
    {
        return $this->percentage;
    }

    public function setPercentage(string $percentage): self
    {
        $this->percentage = $percentage;
        return $this;
    }

    public function getCommissionAmount(): ?string
    {
        return $this->commissionAmount;
    }

    public function setCommissionAmount(string $commissionAmount): self
    {
        $this->commissionAmount = $commissionAmount;
        return $this;
    }

    public function getPaymentStatus(): CommissionPaymentStatus
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(CommissionPaymentStatus $paymentStatus): self
    {
        $this->paymentStatus = $paymentStatus;
        return $this;
    }


}
