<?php

namespace App\Entity;

use App\Repository\WorksheetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorksheetRepository::class)]
#[ORM\Table(name: 'worksheet')]
#[ORM\Index(name: 'IDX_WORKSHEET_TYPE', columns: ['worksheet_type_id'])]
#[ORM\Index(name: 'IDX_WORKSHEET_STATUS_TYPE', columns: ['worksheet_status_type_id'])]
#[ORM\Index(name: 'IDX_WORKSHEET_PARTNER', columns: ['partner_id'])]
#[ORM\Index(name: 'IDX_WORKSHEET_MACHINE', columns: ['machine_id'])]
#[ORM\Index(name: 'IDX_WORKSHEET_MACHINE_RENTAL', columns: ['machine_rental_id'])]
#[ORM\Index(name: 'IDX_WORKSHEET_COMPANY_SITE', columns: ['company_site_id'])]
#[ORM\UniqueConstraint(name: 'UNIQ_WORKSHEET_CODE', columns: ['code'])]
class Worksheet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'worksheet_type_id', nullable: false)]
    private ?WorksheetType $worksheetType = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'worksheet_status_type_id', nullable: false)]
    private ?WorksheetStatusType $worksheetStatusType = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'partner_id', nullable: true, onDelete: 'SET NULL')]
    private ?Partner $partner = null;

    #[ORM\Column(name: 'machine_id', nullable: true)]
    private ?int $machineId = null;

    #[ORM\Column(name: 'machine_rental_id', nullable: true)]
    private ?int $machineRentalId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'company_site_id', nullable: true)]
    private ?CompanySite $companySite = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $data = null;

    #[ORM\Column(length: 32, options: ['default' => '1'])]
    private string $status = '1';

    #[ORM\Column(name: 'uid_add', nullable: true)]
    private ?int $uidAdd = null;

    #[ORM\Column(name: 'uid_last', nullable: true)]
    private ?int $uidLast = null;

    #[ORM\Column(name: 'datetime_add', nullable: true)]
    private ?\DateTimeImmutable $datetimeAdd = null;

    #[ORM\Column(name: 'datetime_last', nullable: true)]
    private ?\DateTimeImmutable $datetimeLast = null;

    #[ORM\Column(name: 'datetime_open', nullable: true)]
    private ?\DateTimeImmutable $datetimeOpen = null;

    #[ORM\Column(name: 'datetime_closed', nullable: true)]
    private ?\DateTimeImmutable $datetimeClosed = null;

    public function getId(): ?int { return $this->id; }
    public function getWorksheetType(): ?WorksheetType { return $this->worksheetType; }
    public function setWorksheetType(?WorksheetType $worksheetType): static { $this->worksheetType = $worksheetType; return $this; }
    public function getWorksheetStatusType(): ?WorksheetStatusType { return $this->worksheetStatusType; }
    public function setWorksheetStatusType(?WorksheetStatusType $worksheetStatusType): static { $this->worksheetStatusType = $worksheetStatusType; return $this; }
    public function getPartner(): ?Partner { return $this->partner; }
    public function setPartner(?Partner $partner): static { $this->partner = $partner; return $this; }
    public function getMachineId(): ?int { return $this->machineId; }
    public function setMachineId(?int $machineId): static { $this->machineId = $machineId; return $this; }
    public function getMachineRentalId(): ?int { return $this->machineRentalId; }
    public function setMachineRentalId(?int $machineRentalId): static { $this->machineRentalId = $machineRentalId; return $this; }
    public function getCompanySite(): ?CompanySite { return $this->companySite; }
    public function setCompanySite(?CompanySite $companySite): static { $this->companySite = $companySite; return $this; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }
    public function getData(): ?array { return $this->data; }
    public function setData(?array $data): static { $this->data = $data; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getUidAdd(): ?int { return $this->uidAdd; }
    public function setUidAdd(?int $uidAdd): static { $this->uidAdd = $uidAdd; return $this; }
    public function getUidLast(): ?int { return $this->uidLast; }
    public function setUidLast(?int $uidLast): static { $this->uidLast = $uidLast; return $this; }
    public function getDatetimeAdd(): ?\DateTimeImmutable { return $this->datetimeAdd; }
    public function setDatetimeAdd(?\DateTimeImmutable $datetimeAdd): static { $this->datetimeAdd = $datetimeAdd; return $this; }
    public function getDatetimeLast(): ?\DateTimeImmutable { return $this->datetimeLast; }
    public function setDatetimeLast(?\DateTimeImmutable $datetimeLast): static { $this->datetimeLast = $datetimeLast; return $this; }
    public function getDatetimeOpen(): ?\DateTimeImmutable { return $this->datetimeOpen; }
    public function setDatetimeOpen(?\DateTimeImmutable $datetimeOpen): static { $this->datetimeOpen = $datetimeOpen; return $this; }
    public function getDatetimeClosed(): ?\DateTimeImmutable { return $this->datetimeClosed; }
    public function setDatetimeClosed(?\DateTimeImmutable $datetimeClosed): static { $this->datetimeClosed = $datetimeClosed; return $this; }
}
