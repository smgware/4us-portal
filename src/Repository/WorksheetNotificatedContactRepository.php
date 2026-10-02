<?php

namespace App\Repository;

use App\Entity\Partner;
use App\Entity\PartnerContact;
use App\Entity\Worksheet;
use App\Entity\WorksheetNotificatedContact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class WorksheetNotificatedContactRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorksheetNotificatedContact::class);
        $this->entityManager = $this->getEntityManager();
    }

    /**
     * @return int[]
     */
    public function findContactIds(Worksheet $worksheet): array
    {
        $rows = $this->createQueryBuilder('notificationContact')
            ->select('IDENTITY(notificationContact.partnerContact) AS contact_id')
            ->andWhere('notificationContact.worksheet = :worksheet')
            ->setParameter('worksheet', $worksheet)
            ->orderBy('notificationContact.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(
            static fn (array $row): int => (int) $row['contact_id'],
            $rows,
        ));
    }

    /**
     * Replaces every notification contact assigned to the worksheet.
     *
     * @param PartnerContact[] $contacts
     */
    public function replaceForWorksheet(
        Worksheet $worksheet,
        ?Partner $partner,
        array $contacts,
    ): void {
        $desiredContacts = [];
        foreach ($contacts as $contact) {
            if (
                !$contact instanceof PartnerContact
                || $partner === null
                || $contact->getPartner()?->getId() !== $partner->getId()
            ) {
                throw new \InvalidArgumentException('A kapcsolattartó nem tartozik a kiválasztott partnerhez.');
            }

            if ($contact->getId()) {
                $desiredContacts[$contact->getId()] = $contact;
            }
        }

        if ($worksheet->getId() !== null) {
            foreach ($this->findBy(['worksheet' => $worksheet]) as $existingContact) {
                $contactId = $existingContact->getPartnerContact()?->getId();
                if ($partner === null || !$contactId || !isset($desiredContacts[$contactId])) {
                    $this->entityManager->remove($existingContact);
                    continue;
                }

                $existingContact->setPartner($partner);
                unset($desiredContacts[$contactId]);
            }
        }

        if ($partner === null) {
            return;
        }

        foreach ($desiredContacts as $contact) {

            $notificationContact = (new WorksheetNotificatedContact())
                ->setWorksheet($worksheet)
                ->setPartner($partner)
                ->setPartnerContact($contact);

            $this->entityManager->persist($notificationContact);
        }
    }
}
