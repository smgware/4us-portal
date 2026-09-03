<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WorksheetType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class WorksheetTypeRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorksheetType::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(WorksheetType $worksheetType, bool $flush = true): void
    {
        $this->entityManager->persist($worksheetType);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function delete(WorksheetType $worksheetType, bool $flush = true): void
    {
        $this->entityManager->remove($worksheetType);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function findList(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(WorksheetType::class, 'worksheetType')
            ->leftJoin(User::class, 'userOwner', 'WITH', 'userOwner.id = worksheetType.uidAdd')
            ->leftJoin(User::class, 'userEditor', 'WITH', 'userEditor.id = worksheetType.uidLast');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('worksheetType.title LIKE :search OR worksheetType.code LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(worksheetType.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->select([
                'worksheetType.id AS id',
                'worksheetType.title AS title',
                'worksheetType.code AS code',
                'worksheetType.uidAdd AS uid_add',
                'worksheetType.uidLast AS uid_last',
                'worksheetType.datetimeAdd AS datetime_add',
                'worksheetType.datetimeLast AS datetime_last',
                'worksheetType.status AS status',
                'userOwner.id AS user_owner_id',
                'userOwner.name AS user_owner_name',
                'userOwner.userName AS user_owner_username',
                'userEditor.id AS user_editor_id',
                'userEditor.name AS user_editor_name',
                'userEditor.userName AS user_editor_username',
            ])
            ->orderBy('worksheetType.id', 'DESC')
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
