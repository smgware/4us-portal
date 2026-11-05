<?php

namespace App\Entity;

use App\Repository\QuoteMakerQuoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuoteMakerQuoteRepository::class)]
#[ORM\Table(name: 'quote_maker_quote')]
#[ORM\Index(name: 'IDX_QUOTE_MAKER_QUOTE_CODE', columns: ['code'])]
class QuoteMakerQuote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $data = null;

    #[ORM\Column(name: 'datetime_add', nullable: true)]
    private ?\DateTimeImmutable $datetimeAdd = null;

    #[ORM\Column(name: 'datetime_last', nullable: true)]
    private ?\DateTimeImmutable $datetimeLast = null;

    #[ORM\Column(name: 'uid_add', nullable: true)]
    private ?int $uidAdd = null;

    #[ORM\Column(name: 'uid_last', nullable: true)]
    private ?int $uidLast = null;

    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): static { $this->title = $title; return $this; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $code): static { $this->code = $code; return $this; }
    public function getData(): ?array { return $this->data; }
    public function setData(?array $data): static { $this->data = $data; return $this; }
    public function getDatetimeAdd(): ?\DateTimeImmutable { return $this->datetimeAdd; }
    public function setDatetimeAdd(?\DateTimeImmutable $datetimeAdd): static { $this->datetimeAdd = $datetimeAdd; return $this; }
    public function getDatetimeLast(): ?\DateTimeImmutable { return $this->datetimeLast; }
    public function setDatetimeLast(?\DateTimeImmutable $datetimeLast): static { $this->datetimeLast = $datetimeLast; return $this; }
    public function getUidAdd(): ?int { return $this->uidAdd; }
    public function setUidAdd(?int $uidAdd): static { $this->uidAdd = $uidAdd; return $this; }
    public function getUidLast(): ?int { return $this->uidLast; }
    public function setUidLast(?int $uidLast): static { $this->uidLast = $uidLast; return $this; }
}
