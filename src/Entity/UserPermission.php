<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_permission')]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_PERMISSION_USER_PERMISSION', columns: ['user_id', 'permission_id'])]
class UserPermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userPermissions')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'permission_id', nullable: false, onDelete: 'CASCADE')]
    private ?Permission $permission = null;

    #[ORM\Column(name: 'uid_add', nullable: true)]
    private ?int $uidAdd = null;

    #[ORM\Column(name: 'uid_last', nullable: true)]
    private ?int $uidLast = null;

    #[ORM\Column(name: 'datetime_add', nullable: true)]
    private ?\DateTime $datetimeAdd = null;

    #[ORM\Column(name: 'datetime_last', nullable: true)]
    private ?\DateTime $datetimeLast = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getPermission(): ?Permission
    {
        return $this->permission;
    }

    public function setPermission(Permission $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    public function getUidAdd(): ?int
    {
        return $this->uidAdd;
    }

    public function setUidAdd(?int $uidAdd): static
    {
        $this->uidAdd = $uidAdd;

        return $this;
    }

    public function getUidLast(): ?int
    {
        return $this->uidLast;
    }

    public function setUidLast(?int $uidLast): static
    {
        $this->uidLast = $uidLast;

        return $this;
    }

    public function getDatetimeAdd(): ?\DateTime
    {
        return $this->datetimeAdd;
    }

    public function setDatetimeAdd(?\DateTime $datetimeAdd): static
    {
        $this->datetimeAdd = $datetimeAdd;

        return $this;
    }

    public function getDatetimeLast(): ?\DateTime
    {
        return $this->datetimeLast;
    }

    public function setDatetimeLast(?\DateTime $datetimeLast): static
    {
        $this->datetimeLast = $datetimeLast;

        return $this;
    }
}
