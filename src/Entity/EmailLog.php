<?php

namespace App\Entity;

use App\Repository\EmailLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailLogRepository::class)]
#[ORM\Table(name: 'email_logs')]
#[ORM\Index(name: 'IDX_EMAIL_LOG_RECIPIENT_EMAIL', columns: ['recipient_email'])]
#[ORM\Index(name: 'IDX_EMAIL_LOG_RECIPIENT_NAME', columns: ['recipient_name'])]
#[ORM\Index(name: 'IDX_EMAIL_LOG_EVENT_NAME', columns: ['event_name'])]
#[ORM\Index(name: 'IDX_EMAIL_LOG_EVENT_CODE', columns: ['event_code'])]
#[ORM\Index(name: 'IDX_EMAIL_LOG_DATETIME_ADD', columns: ['datetime_add'])]
class EmailLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'event_name', length: 255)]
    private ?string $eventName = null;

    #[ORM\Column(name: 'event_code', length: 100)]
    private ?string $eventCode = null;

    #[ORM\Column(name: 'email_html', type: Types::TEXT)]
    private ?string $emailHtml = null;

    #[ORM\Column(name: 'recipient_email', length: 255)]
    private ?string $recipientEmail = null;

    #[ORM\Column(name: 'recipient_name', length: 255)]
    private ?string $recipientName = null;

    #[ORM\Column(name: 'datetime_add')]
    private ?\DateTimeImmutable $datetimeAdd = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventName(): ?string
    {
        return $this->eventName;
    }

    public function setEventName(string $eventName): static
    {
        $this->eventName = $eventName;

        return $this;
    }

    public function getEventCode(): ?string
    {
        return $this->eventCode;
    }

    public function setEventCode(string $eventCode): static
    {
        $this->eventCode = $eventCode;

        return $this;
    }

    public function getEmailHtml(): ?string
    {
        return $this->emailHtml;
    }

    public function setEmailHtml(string $emailHtml): static
    {
        $this->emailHtml = $emailHtml;

        return $this;
    }

    public function getRecipientEmail(): ?string
    {
        return $this->recipientEmail;
    }

    public function setRecipientEmail(string $recipientEmail): static
    {
        $this->recipientEmail = $recipientEmail;

        return $this;
    }

    public function getRecipientName(): ?string
    {
        return $this->recipientName;
    }

    public function setRecipientName(string $recipientName): static
    {
        $this->recipientName = $recipientName;

        return $this;
    }

    public function getDatetimeAdd(): ?\DateTimeImmutable
    {
        return $this->datetimeAdd;
    }

    public function setDatetimeAdd(\DateTimeImmutable $datetimeAdd): static
    {
        $this->datetimeAdd = $datetimeAdd;

        return $this;
    }
}
