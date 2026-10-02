<?php

namespace App\Repository;

use App\Entity\Partner;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class PartnerRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Partner::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(Partner $partner, bool $flush = true): void
    {
        $this->entityManager->persist($partner);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function delete(Partner $partner, bool $flush = true): void
    {
        $this->entityManager->remove($partner);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function findForWorksheetSelect(string $search, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $qb = $this->createQueryBuilder('partner')
            ->select([
                'partner.id AS id',
                'partner.code AS code',
                'partner.name AS name',
                'partner.taxNumber AS tax_number',
                'partner.city AS city',
                'partner.address AS address',
                'partner.status AS status',
            ]);

        $search = trim($search);
        if ($search !== '') {
            $qb
                ->andWhere('(partner.name LIKE :search OR partner.code LIKE :search OR partner.taxNumber LIKE :search OR partner.email LIKE :search OR partner.phone LIKE :search OR partner.city LIKE :search OR partner.address LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb
            ->orderBy('partner.status', 'DESC')
            ->addOrderBy('partner.name', 'ASC')
            ->addOrderBy('partner.code', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function findForMachineRentalSelect(string $search, int $limit = 20): array
    {
        return $this->findForWorksheetSelect($search, $limit);
    }

    public function findList(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(Partner::class, 'partner')
            ->leftJoin(User::class, 'userOwner', 'WITH', 'userOwner.id = partner.uidAdd')
            ->leftJoin(User::class, 'userEditor', 'WITH', 'userEditor.id = partner.uidLast');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('partner.code LIKE :search OR partner.name LIKE :search OR partner.taxNumber LIKE :search OR partner.email LIKE :search OR partner.phone LIKE :search OR partner.city LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(partner.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->select([
                'partner.id AS id',
                'partner.code AS code',
                'partner.name AS name',
                'partner.type AS type',
                'partner.taxNumber AS tax_number',
                'partner.email AS email',
                'partner.phone AS phone',
                'partner.website AS website',
                'partner.country AS country',
                'partner.zip AS zip',
                'partner.city AS city',
                'partner.address AS address',
                'partner.description AS description',
                'partner.status AS status',
                'partner.datetimeAdd AS datetime_add',
                'partner.datetimeLast AS datetime_last',
                'partner.uidAdd AS uid_add',
                'partner.uidLast AS uid_last',
                'userOwner.id AS user_owner_id',
                'userOwner.name AS user_owner_name',
                'userOwner.userName AS user_owner_username',
                'userEditor.id AS user_editor_id',
                'userEditor.name AS user_editor_name',
                'userEditor.userName AS user_editor_username',
            ])
            ->orderBy('partner.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getArrayResult();

        return [
            'records' => $records,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'itemsPerPage' => $itemsPerPage,
        ];
    }
}
