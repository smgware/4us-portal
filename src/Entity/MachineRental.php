<?php

namespace App\Entity;

use App\Repository\MachineRentalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MachineRentalRepository::class)]
#[ORM\Table(name: 'machine_rental')]
#[ORM\Index(name: 'IDX_MACHINE_RENTAL_PARTNER', columns: ['partner_id'])]
#[ORM\Index(name: 'IDX_MACHINE_RENTAL_MACHINE', columns: ['machine_id'])]
#[ORM\Index(name: 'IDX_MACHINE_RENTAL_PROJECT', columns: ['project_id'])]
#[ORM\Index(name: 'IDX_MACHINE_RENTAL_STATUS', columns: ['status'])]
#[ORM\UniqueConstraint(name: 'UNIQ_MACHINE_RENTAL_CODE', columns: ['code'])]
class MachineRental
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'partner_id', nullable: false)]
    private ?Partner $partner = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'machine_id', nullable: false)]
    private ?Machine $machine = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'project_id', nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\Column(name: 'datetime_rental_start', nullable: true)]
    private ?\DateTimeImmutable $datetimeRentalStart = null;

    #[ORM\Column(name: 'datetime_rental_end', nullable: true)]
    private ?\DateTimeImmutable $datetimeRentalEnd = null;

    #[ORM\Column(length: 255, options: ['default' => '1'])]
    private string $status = '1';

    #[ORM\Column(name: 'uid_add', nullable: true)]
    private ?int $uidAdd = null;

    #[ORM\Column(name: 'uid_last', nullable: true)]
    private ?int $uidLast = null;

    #[ORM\Column(name: 'datetime_add', nullable: true)]
    private ?\DateTimeImmutable $datetimeAdd = null;

    #[ORM\Column(name: 'datetime_last', nullable: true)]
    private ?\DateTimeImmutable $datetimeLast = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getPartner(): ?Partner
    {
        return $this->partner;
    }

    public function setPartner(?Partner $partner): static
    {
        $this->partner = $partner;

        return $this;
    }

    public function getMachine(): ?Machine
    {
        return $this->machine;
    }

    public function setMachine(?Machine $machine): static
    {
        $this->machine = $machine;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getDatetimeRentalStart(): ?\DateTimeImmutable
    {
        return $this->datetimeRentalStart;
    }

    public function setDatetimeRentalStart(?\DateTimeImmutable $datetimeRentalStart): static
    {
        $this->datetimeRentalStart = $datetimeRentalStart;

        return $this;
    }

    public function getDatetimeRentalEnd(): ?\DateTimeImmutable
    {
        return $this->datetimeRentalEnd;
    }

    public function setDatetimeRentalEnd(?\DateTimeImmutable $datetimeRentalEnd): static
    {
        $this->datetimeRentalEnd = $datetimeRentalEnd;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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

    public function getDatetimeAdd(): ?\DateTimeImmutable
    {
        return $this->datetimeAdd;
    }

    public function setDatetimeAdd(?\DateTimeImmutable $datetimeAdd): static
    {
        $this->datetimeAdd = $datetimeAdd;

        return $this;
    }

    public function getDatetimeLast(): ?\DateTimeImmutable
    {
        return $this->datetimeLast;
    }

    public function setDatetimeLast(?\DateTimeImmutable $datetimeLast): static
    {
        $this->datetimeLast = $datetimeLast;

        return $this;
    }
}
