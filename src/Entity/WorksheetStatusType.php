<?php

namespace App\Entity;

use App\Repository\WorksheetStatusTypeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorksheetStatusTypeRepository::class)]
#[ORM\Table(name: 'worksheet_status_types')]
#[ORM\UniqueConstraint(name: 'UNIQ_41A185B01119A96D77153098', columns: ['worksheet_type_id', 'code'])]
class WorksheetStatusType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'worksheet_type_id', nullable: false, onDelete: 'CASCADE')]
    private ?WorksheetType $worksheetType = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

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

    public function getWorksheetType(): ?WorksheetType
    {
        return $this->worksheetType;
    }

    public function setWorksheetType(?WorksheetType $worksheetType): static
    {
        $this->worksheetType = $worksheetType;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
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
