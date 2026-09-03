<?php

namespace App\Repository;

use App\Entity\Permission;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class PermissionRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(Permission $permission, bool $flush = true): void
    {
        $this->entityManager->persist($permission);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function delete(Permission $permission, bool $flush = true): void
    {
        $this->entityManager->remove($permission);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function findParentOptions(?int $excludeId = null): array
    {
        $qb = $this->createQueryBuilder('permission')
            ->select([
                'permission.id AS id',
                'permission.title AS title',
                'permission.code AS code',
            ])
            ->orderBy('permission.title', 'ASC');

        if ($excludeId !== null) {
            $qb
                ->andWhere('permission.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getArrayResult();
    }

    public function findList(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(Permission::class, 'permission')
            ->leftJoin(Permission::class, 'parentPermission', 'WITH', 'parentPermission.id = permission.parentId')
            ->leftJoin(User::class, 'userOwner', 'WITH', 'userOwner.id = permission.uidAdd')
            ->leftJoin(User::class, 'userEditor', 'WITH', 'userEditor.id = permission.uidLast');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('permission.title LIKE :search OR permission.code LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(permission.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->select([
                'permission.id AS id',
                'permission.title AS title',
                'permission.code AS code',
                'permission.parentId AS parent_id',
                'permission.uidAdd AS uid_add',
                'permission.uidLast AS uid_last',
                'permission.datetimeAdd AS datetime_add',
                'permission.datetimeLast AS datetime_last',
                'permission.status AS status',
                'parentPermission.title AS parent_title',
                'parentPermission.code AS parent_code',
                'userOwner.id AS user_owner_id',
                'userOwner.name AS user_owner_name',
                'userOwner.userName AS user_owner_username',
                'userEditor.id AS user_editor_id',
                'userEditor.name AS user_editor_name',
                'userEditor.userName AS user_editor_username',
            ])
            ->orderBy('permission.id', 'DESC')
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
