<?php

namespace App\Entity;

use App\Repository\XReportDetailRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: XReportDetailRepository::class)]
#[ORM\Table(name: 'tbd_x_report_detail')]
#[ORM\HasLifecycleCallbacks]
class XReportDetail extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: XReport::class, inversedBy: 'details')]
    #[ORM\JoinColumn(name: 'x_report_id', referencedColumnName: 'id', nullable: false,
        onDelete: 'CASCADE')]
    private ?XReport $xReport = null;

    #[ORM\ManyToOne(targetEntity: PaymentType::class)]
    #[ORM\JoinColumn(name: 'payment_type_id', referencedColumnName: 'id', nullable: false)]
    private ?PaymentType $paymentType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $systemAmount;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $declaredAmount;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $difference;

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateDifference(): void
    {
        if (function_exists('bcsub')) {
            $this->difference = bcsub($this->systemAmount, $this->declaredAmount, 2);
        } else {
            $this->difference = (string)(floatval($this->systemAmount) - floatval($this->declaredAmount));
        }
    }
    public function __construct()
    {
        $this->systemAmount = '0.00';
        $this->declaredAmount = '0.00';
        $this->difference = '0.00';
        $this->isActive = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getXReport(): ?XReport
    {
        return $this->xReport;
    }

    public function setXReport(?XReport $xReport): void
    {
        $this->xReport = $xReport;
    }

    public function getPaymentType(): ?PaymentType
    {
        return $this->paymentType;
    }

    public function setPaymentType(?PaymentType $paymentType): void
    {
        $this->paymentType = $paymentType;
    }

    public function getSystemAmount(): string
    {
        return $this->systemAmount;
    }

    public function setSystemAmount(string $systemAmount): void
    {
        $this->systemAmount = $systemAmount;
    }

    public function getDeclaredAmount(): string
    {
        return $this->declaredAmount;
    }

    public function setDeclaredAmount(string $declaredAmount): void
    {
        $this->declaredAmount = $declaredAmount;
    }

    public function getDifference(): string
    {
        return $this->difference;
    }

    public function setDifference(string $difference): void
    {
        $this->difference = $difference;
    }


}
