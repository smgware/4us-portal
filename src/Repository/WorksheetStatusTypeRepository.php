<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WorksheetStatusType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class WorksheetStatusTypeRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorksheetStatusType::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(WorksheetStatusType $worksheetStatusType, bool $flush = true): void
    {
        $this->entityManager->persist($worksheetStatusType);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function delete(WorksheetStatusType $worksheetStatusType, bool $flush = true): void
    {
        $this->entityManager->remove($worksheetStatusType);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function findList(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(WorksheetStatusType::class, 'worksheetStatusType')
            ->innerJoin('worksheetStatusType.worksheetType', 'worksheetType')
            ->leftJoin(User::class, 'userOwner', 'WITH', 'userOwner.id = worksheetStatusType.uidAdd')
            ->leftJoin(User::class, 'userEditor', 'WITH', 'userEditor.id = worksheetStatusType.uidLast');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('(worksheetStatusType.title LIKE :search OR worksheetStatusType.code LIKE :search OR worksheetType.title LIKE :search OR worksheetType.code LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $worksheetTypeId = (int) ($filters['worksheet_type_id'] ?? 0);
        if ($worksheetTypeId > 0) {
            $qb
                ->andWhere('worksheetType.id = :worksheetTypeId')
                ->setParameter('worksheetTypeId', $worksheetTypeId);
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(worksheetStatusType.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->select([
                'worksheetStatusType.id AS id',
                'worksheetStatusType.title AS title',
                'worksheetStatusType.code AS code',
                'worksheetStatusType.uidAdd AS uid_add',
                'worksheetStatusType.uidLast AS uid_last',
                'worksheetStatusType.datetimeAdd AS datetime_add',
                'worksheetStatusType.datetimeLast AS datetime_last',
                'worksheetType.id AS worksheet_type_id',
                'worksheetType.title AS worksheet_type_title',
                'worksheetType.code AS worksheet_type_code',
                'userOwner.name AS user_owner_name',
                'userOwner.userName AS user_owner_username',
                'userEditor.name AS user_editor_name',
                'userEditor.userName AS user_editor_username',
            ])
            ->orderBy('worksheetType.title', 'ASC')
            ->addOrderBy('worksheetStatusType.id', 'DESC')
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
