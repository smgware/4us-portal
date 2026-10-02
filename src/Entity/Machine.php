<?php

namespace App\Entity;

use App\Repository\MachineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MachineRepository::class)]
#[ORM\Table(name: 'machine')]
#[ORM\Index(name: 'IDX_MACHINE_PROJECT', columns: ['project_id'])]
#[ORM\Index(name: 'IDX_MACHINE_CATEGORY', columns: ['machine_category_id'])]
#[ORM\Index(name: 'IDX_MACHINE_COMPANY_SITE', columns: ['company_site_id'])]
#[ORM\Index(name: 'IDX_MACHINE_STATUS', columns: ['status'])]
class Machine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'project_id', nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'machine_category_id', nullable: false)]
    private ?MachineCategory $machineCategory = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'company_site_id', nullable: false)]
    private ?CompanySite $companySite = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $data = null;

    #[ORM\Column(name: 'uid_add', nullable: true)]
    private ?int $uidAdd = null;

    #[ORM\Column(name: 'uid_last', nullable: true)]
    private ?int $uidLast = null;

    #[ORM\Column(name: 'datetime_add', nullable: true)]
    private ?\DateTimeImmutable $datetimeAdd = null;

    #[ORM\Column(name: 'datetime_last', nullable: true)]
    private ?\DateTimeImmutable $datetimeLast = null;

    #[ORM\Column(length: 255, options: ['default' => '1'])]
    private string $status = '1';

    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }
    public function getProject(): ?Project { return $this->project; }
    public function setProject(?Project $project): static { $this->project = $project; return $this; }
    public function getMachineCategory(): ?MachineCategory { return $this->machineCategory; }
    public function setMachineCategory(?MachineCategory $machineCategory): static { $this->machineCategory = $machineCategory; return $this; }
    public function getCompanySite(): ?CompanySite { return $this->companySite; }
    public function setCompanySite(?CompanySite $companySite): static { $this->companySite = $companySite; return $this; }
    public function getData(): ?array { return $this->data; }
    public function setData(?array $data): static { $this->data = $data; return $this; }
    public function getUidAdd(): ?int { return $this->uidAdd; }
    public function setUidAdd(?int $uidAdd): static { $this->uidAdd = $uidAdd; return $this; }
    public function getUidLast(): ?int { return $this->uidLast; }
    public function setUidLast(?int $uidLast): static { $this->uidLast = $uidLast; return $this; }
    public function getDatetimeAdd(): ?\DateTimeImmutable { return $this->datetimeAdd; }
    public function setDatetimeAdd(?\DateTimeImmutable $datetimeAdd): static { $this->datetimeAdd = $datetimeAdd; return $this; }
    public function getDatetimeLast(): ?\DateTimeImmutable { return $this->datetimeLast; }
    public function setDatetimeLast(?\DateTimeImmutable $datetimeLast): static { $this->datetimeLast = $datetimeLast; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
}
