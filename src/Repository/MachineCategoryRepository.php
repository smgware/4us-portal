<?php

namespace App\Repository;

use App\Entity\MachineCategory;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class MachineCategoryRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MachineCategory::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(MachineCategory $machineCategory, bool $flush = true): void
    {
        $this->entityManager->persist($machineCategory);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function delete(MachineCategory $machineCategory, bool $flush = true): void
    {
        $this->entityManager->remove($machineCategory);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function findList(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(MachineCategory::class, 'machineCategory')
            ->leftJoin(User::class, 'userOwner', 'WITH', 'userOwner.id = machineCategory.uidAdd')
            ->leftJoin(User::class, 'userEditor', 'WITH', 'userEditor.id = machineCategory.uidLast');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('machineCategory.title LIKE :search OR machineCategory.code LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(machineCategory.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->select([
                'machineCategory.id AS id',
                'machineCategory.title AS title',
                'machineCategory.code AS code',
                'machineCategory.uidAdd AS uid_add',
                'machineCategory.uidLast AS uid_last',
                'machineCategory.datetimeAdd AS datetime_add',
                'machineCategory.datetimeLast AS datetime_last',
                'machineCategory.status AS status',
                'userOwner.id AS user_owner_id',
                'userOwner.name AS user_owner_name',
                'userOwner.userName AS user_owner_username',
                'userEditor.id AS user_editor_id',
                'userEditor.name AS user_editor_name',
                'userEditor.userName AS user_editor_username',
            ])
            ->orderBy('machineCategory.id', 'DESC')
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
