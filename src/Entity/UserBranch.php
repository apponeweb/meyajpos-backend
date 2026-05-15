<?php

namespace App\Entity;

use App\Repository\UserBranchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserBranchRepository::class)]
#[ORM\Table(name: 'tbd_user_branch')]
#[ORM\UniqueConstraint(name: 'uq_user_branch', columns: ['user_id', 'branch_id'])]
class UserBranch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Branch $branch;

    #[ORM\Column(name: 'is_default', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isDefault = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): void { $this->user = $user; }

    public function getBranch(): Branch { return $this->branch; }
    public function setBranch(Branch $branch): void { $this->branch = $branch; }

    public function isDefault(): bool { return $this->isDefault; }
    public function setIsDefault(bool $isDefault): void { $this->isDefault = $isDefault; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
