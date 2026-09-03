<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_notification_event')]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_NOTIFICATION_EVENT_USER_EVENT', columns: ['user_id', 'notification_event_id'])]
class UserNotificationEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userNotificationEvents')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'notification_event_id', nullable: false, onDelete: 'CASCADE')]
    private ?NotificationEvent $notificationEvent = null;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getNotificationEvent(): ?NotificationEvent
    {
        return $this->notificationEvent;
    }

    public function setNotificationEvent(NotificationEvent $notificationEvent): static
    {
        $this->notificationEvent = $notificationEvent;

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
