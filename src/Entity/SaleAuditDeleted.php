<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tbd_sale_audit_deleted')]
class SaleAuditDeleted
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 30)]
    private string $folio;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private string $total;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $deletedAt;

    #[ORM\Column(type: Types::JSON)]
    private array $content = [];

    public function __construct()
    {
        $this->deletedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getFolio(): string
    {
        return $this->folio;
    }

    public function setFolio(string $folio): void
    {
        $this->folio = $folio;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): void
    {
        $this->total = $total;
    }

    public function getDeletedAt(): \DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(\DateTimeInterface $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function setContent(array $content): void
    {
        $this->content = $content;
    }


}
