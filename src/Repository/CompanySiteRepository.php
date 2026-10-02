<?php

namespace App\Repository;

use App\Entity\CompanySite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class CompanySiteRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanySite::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(CompanySite $companySite, bool $flush = true): void
    {
        $this->entityManager->persist($companySite);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function delete(CompanySite $companySite, bool $flush = true): void
    {
        $this->entityManager->remove($companySite);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function findList(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(CompanySite::class, 'companySite')
            ->leftJoin(User::class, 'userOwner', 'WITH', 'userOwner.id = companySite.uidAdd')
            ->leftJoin(User::class, 'userEditor', 'WITH', 'userEditor.id = companySite.uidLast');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('companySite.title LIKE :search OR companySite.code LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(companySite.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->select([
                'companySite.id AS id',
                'companySite.title AS title',
                'companySite.code AS code',
                'companySite.uidAdd AS uid_add',
                'companySite.uidLast AS uid_last',
                'companySite.datetimeAdd AS datetime_add',
                'companySite.datetimeLast AS datetime_last',
                'companySite.status AS status',
                'userOwner.name AS user_owner_name',
                'userOwner.userName AS user_owner_username',
                'userEditor.name AS user_editor_name',
                'userEditor.userName AS user_editor_username',
            ])
            ->orderBy('companySite.id', 'DESC')
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
