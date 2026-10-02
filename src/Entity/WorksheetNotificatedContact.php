<?php

namespace App\Entity;

use App\Repository\WorksheetNotificatedContactRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorksheetNotificatedContactRepository::class)]
#[ORM\Table(name: 'worksheet_notificated_contact')]
#[ORM\Index(name: 'IDX_WORKSHEET_NOTIFICATED_WORKSHEET', columns: ['worksheet_id'])]
#[ORM\Index(name: 'IDX_WORKSHEET_NOTIFICATED_PARTNER', columns: ['partner_id'])]
#[ORM\Index(name: 'IDX_WORKSHEET_NOTIFICATED_CONTACT', columns: ['partner_contact_id'])]
#[ORM\UniqueConstraint(
    name: 'UNIQ_WORKSHEET_NOTIFICATED_CONTACT',
    columns: ['worksheet_id', 'partner_contact_id'],
)]
class WorksheetNotificatedContact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'worksheet_id', nullable: false, onDelete: 'CASCADE')]
    private ?Worksheet $worksheet = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'partner_id', nullable: false, onDelete: 'CASCADE')]
    private ?Partner $partner = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'partner_contact_id', nullable: false, onDelete: 'CASCADE')]
    private ?PartnerContact $partnerContact = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorksheet(): ?Worksheet
    {
        return $this->worksheet;
    }

    public function setWorksheet(Worksheet $worksheet): static
    {
        $this->worksheet = $worksheet;

        return $this;
    }

    public function getPartner(): ?Partner
    {
        return $this->partner;
    }

    public function setPartner(Partner $partner): static
    {
        $this->partner = $partner;

        return $this;
    }

    public function getPartnerContact(): ?PartnerContact
    {
        return $this->partnerContact;
    }

    public function setPartnerContact(PartnerContact $partnerContact): static
    {
        $this->partnerContact = $partnerContact;

        return $this;
    }
}
