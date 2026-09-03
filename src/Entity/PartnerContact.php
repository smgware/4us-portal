<?php

namespace App\Entity;

use App\Repository\PartnerContactRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PartnerContactRepository::class)]
#[ORM\Table(name: 'partner_contact')]
class PartnerContact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'partner_id', nullable: false, onDelete: 'CASCADE')]
    private ?Partner $partner = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'default_contact', options: ['default' => false])]
    private bool $defaultContact = false;

    #[ORM\Column(name: 'datetime_add', nullable: true)]
    private ?\DateTimeImmutable $datetimeAdd = null;

    #[ORM\Column(name: 'datetime_last', nullable: true)]
    private ?\DateTimeImmutable $datetimeLast = null;

    #[ORM\Column(name: 'uid_add', nullable: true)]
    private ?int $uidAdd = null;

    #[ORM\Column(name: 'uid_last', nullable: true)]
    private ?int $uidLast = null;

    public function getId(): ?int { return $this->id; }
    public function getPartner(): ?Partner { return $this->partner; }
    public function setPartner(Partner $partner): static { $this->partner = $partner; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): static { $this->title = $title; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function isDefaultContact(): bool { return $this->defaultContact; }
    public function setDefaultContact(bool $defaultContact): static { $this->defaultContact = $defaultContact; return $this; }
    public function getDatetimeAdd(): ?\DateTimeImmutable { return $this->datetimeAdd; }
    public function setDatetimeAdd(?\DateTimeImmutable $datetimeAdd): static { $this->datetimeAdd = $datetimeAdd; return $this; }
    public function getDatetimeLast(): ?\DateTimeImmutable { return $this->datetimeLast; }
    public function setDatetimeLast(?\DateTimeImmutable $datetimeLast): static { $this->datetimeLast = $datetimeLast; return $this; }
    public function getUidAdd(): ?int { return $this->uidAdd; }
    public function setUidAdd(?int $uidAdd): static { $this->uidAdd = $uidAdd; return $this; }
    public function getUidLast(): ?int { return $this->uidLast; }
    public function setUidLast(?int $uidLast): static { $this->uidLast = $uidLast; return $this; }
}
