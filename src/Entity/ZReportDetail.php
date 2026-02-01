<?php

namespace App\Entity;

use App\Repository\ZReportDetailRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ZReportDetailRepository::class)]
#[ORM\Table(name: 'tbd_z_report_detail')]
#[ORM\HasLifecycleCallbacks]
class ZReportDetail extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ZReport::class, inversedBy: 'details')]
    #[ORM\JoinColumn(name: 'z_report_id', referencedColumnName: 'id', nullable: false)]
    private ?ZReport $zReport = null;

    #[ORM\ManyToOne(targetEntity: PaymentType::class)]
    #[ORM\JoinColumn(name: 'payment_type_id', referencedColumnName: 'id', nullable: false)]
    private ?PaymentType $paymentType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(type: Types::INTEGER)]
    private int $transactionCount = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getZReport(): ?ZReport
    {
        return $this->zReport;
    }

    public function setZReport(?ZReport $zReport): void
    {
        $this->zReport = $zReport;
    }

    public function getPaymentType(): ?PaymentType
    {
        return $this->paymentType;
    }

    public function setPaymentType(?PaymentType $paymentType): void
    {
        $this->paymentType = $paymentType;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): void
    {
        $this->amount = $amount;
    }

    public function getTransactionCount(): int
    {
        return $this->transactionCount;
    }

    public function setTransactionCount(int $transactionCount): void
    {
        $this->transactionCount = $transactionCount;
    }

    // --- Getters y Setters ---

}
