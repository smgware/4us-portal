<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
//#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['userName'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(name: 'username', length: 180)]
    private ?string $userName = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 120)]
    private ?string $name = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'permission_id', nullable: true)]
    private ?Permission $permission = null;

    /** @var Collection<int, UserPermission> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserPermission::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $userPermissions;

    /** @var Collection<int, UserNotificationEvent> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserNotificationEvent::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $userNotificationEvents;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(name: 'PasswordRecoveryToken', length: 64, nullable: true)]
    private ?string $passwordRecoveryToken = null;

    public function __construct()
    {
        $this->userPermissions = new ArrayCollection();
        $this->userNotificationEvents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(string $userName): static
    {
        $this->userName = $userName;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->userName;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;

        foreach ($this->userPermissions as $userPermission) {
            $permissionCode = trim((string) $userPermission->getPermission()?->getCode());
            if ($permissionCode !== '') {
                $roles[] = str_starts_with($permissionCode, 'ROLE_')
                    ? $permissionCode
                    : 'ROLE_' . $permissionCode;
            }
        }

        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPermission(): ?Permission
    {
        return $this->permission;
    }

    public function setPermission(?Permission $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    /** @return Collection<int, UserPermission> */
    public function getUserPermissions(): Collection
    {
        return $this->userPermissions;
    }

    public function addUserPermission(UserPermission $userPermission): static
    {
        if (!$this->userPermissions->contains($userPermission)) {
            $this->userPermissions->add($userPermission);
            $userPermission->setUser($this);
        }

        return $this;
    }

    public function removeUserPermission(UserPermission $userPermission): static
    {
        if ($this->userPermissions->removeElement($userPermission)
            && $userPermission->getUser() === $this) {
            $userPermission->setUser(null);
        }

        return $this;
    }

    /** @return Collection<int, UserNotificationEvent> */
    public function getUserNotificationEvents(): Collection
    {
        return $this->userNotificationEvents;
    }

    public function addUserNotificationEvent(UserNotificationEvent $userNotificationEvent): static
    {
        if (!$this->userNotificationEvents->contains($userNotificationEvent)) {
            $this->userNotificationEvents->add($userNotificationEvent);
            $userNotificationEvent->setUser($this);
        }

        return $this;
    }

    public function removeUserNotificationEvent(UserNotificationEvent $userNotificationEvent): static
    {
        if ($this->userNotificationEvents->removeElement($userNotificationEvent)
            && $userNotificationEvent->getUser() === $this) {
            $userNotificationEvent->setUser(null);
        }

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPasswordRecoveryToken(): ?string
    {
        return $this->passwordRecoveryToken;
    }

    public function setPasswordRecoveryToken(?string $passwordRecoveryToken): static
    {
        $this->passwordRecoveryToken = $passwordRecoveryToken;

        return $this;
    }
}
