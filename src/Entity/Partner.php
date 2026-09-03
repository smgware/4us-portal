<?php

namespace App\Entity;

use App\Repository\PartnerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PartnerRepository::class)]
#[ORM\Table(name: 'partner')]
class Partner
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $taxNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $zip = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(name: 'datetime_add', nullable: true)]
    private ?\DateTimeImmutable $datetimeAdd = null;

    #[ORM\Column(name: 'datetime_last', nullable: true)]
    private ?\DateTimeImmutable $datetimeLast = null;

    #[ORM\Column(name: 'uid_add', nullable: true)]
    private ?int $uidAdd = null;

    #[ORM\Column(name: 'uid_last', nullable: true)]
    private ?int $uidLast = null;

    public function getId(): ?int { return $this->id; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(?string $type): static { $this->type = $type; return $this; }
    public function getTaxNumber(): ?string { return $this->taxNumber; }
    public function setTaxNumber(?string $taxNumber): static { $this->taxNumber = $taxNumber; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }
    public function getWebsite(): ?string { return $this->website; }
    public function setWebsite(?string $website): static { $this->website = $website; return $this; }
    public function getCountry(): ?string { return $this->country; }
    public function setCountry(?string $country): static { $this->country = $country; return $this; }
    public function getZip(): ?string { return $this->zip; }
    public function setZip(?string $zip): static { $this->zip = $zip; return $this; }
    public function getCity(): ?string { return $this->city; }
    public function setCity(?string $city): static { $this->city = $city; return $this; }
    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): static { $this->address = $address; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getDatetimeAdd(): ?\DateTimeImmutable { return $this->datetimeAdd; }
    public function setDatetimeAdd(?\DateTimeImmutable $datetimeAdd): static { $this->datetimeAdd = $datetimeAdd; return $this; }
    public function getDatetimeLast(): ?\DateTimeImmutable { return $this->datetimeLast; }
    public function setDatetimeLast(?\DateTimeImmutable $datetimeLast): static { $this->datetimeLast = $datetimeLast; return $this; }
    public function getUidAdd(): ?int { return $this->uidAdd; }
    public function setUidAdd(?int $uidAdd): static { $this->uidAdd = $uidAdd; return $this; }
    public function getUidLast(): ?int { return $this->uidLast; }
    public function setUidLast(?int $uidLast): static { $this->uidLast = $uidLast; return $this; }
}
