<?php

namespace App\Repository;

use App\Entity\Partner;
use App\Entity\PartnerContact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class PartnerContactRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PartnerContact::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(PartnerContact $contact, bool $flush = true): void
    {
        $this->entityManager->persist($contact);
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function delete(PartnerContact $contact, bool $flush = true): void
    {
        $this->entityManager->remove($contact);
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * Returns the explicitly selected contacts and every default contact of the partner.
     *
     * @param int[] $selectedContactIds
     * @return PartnerContact[]
     */
    public function findWorksheetNotificationRecipients(Partner $partner, array $selectedContactIds): array
    {
        $selectedContactIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $selectedContactIds),
            static fn (int $id): bool => $id > 0,
        )));

        $qb = $this->createQueryBuilder('contact')
            ->andWhere('contact.partner = :partner')
            ->andWhere('contact.email IS NOT NULL')
            ->andWhere("contact.email <> ''")
            ->setParameter('partner', $partner);

        if ($selectedContactIds === []) {
            $qb->andWhere('contact.defaultContact = :defaultContact');
        } else {
            $qb
                ->andWhere($qb->expr()->orX(
                    'contact.defaultContact = :defaultContact',
                    'contact.id IN (:selectedContactIds)',
                ))
                ->setParameter('selectedContactIds', $selectedContactIds);
        }

        return $qb
            ->setParameter('defaultContact', true)
            ->orderBy('contact.defaultContact', 'DESC')
            ->addOrderBy('contact.name', 'ASC')
            ->addOrderBy('contact.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findList(Partner $partner, array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);
        $qb = $this->createQueryBuilder('contact')
            ->andWhere('contact.partner = :partner')
            ->setParameter('partner', $partner);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb->andWhere('contact.name LIKE :search OR contact.title LIKE :search OR contact.email LIKE :search OR contact.phone LIKE :search OR contact.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb->select('COUNT(contact.id)')->getQuery()->getSingleScalarResult();
        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);

        $records = $qb
            ->orderBy('contact.name', 'ASC')
            ->addOrderBy('contact.id', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return compact('records', 'page', 'totalPages', 'totalRecords', 'itemsPerPage');
    }
}
