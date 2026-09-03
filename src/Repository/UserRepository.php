<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(User $user, bool $flush = true): void
    {
        $this->entityManager->persist($user);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function delete(User $user, bool $flush = true): void
    {
        $this->entityManager->remove($user);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * @return User[]
     */
    public function findUsersByNotificationEventCode(string $notificationEventCode): array
    {
        $notificationEventCode = trim($notificationEventCode);
        if ($notificationEventCode === '') {
            return [];
        }

        return $this->createQueryBuilder('user')
            ->select('DISTINCT user')
            ->innerJoin('user.userNotificationEvents', 'userNotificationEvent')
            ->innerJoin('userNotificationEvent.notificationEvent', 'notificationEvent')
            ->andWhere('notificationEvent.code = :notificationEventCode')
            ->andWhere('notificationEvent.status = :activeEventStatus')
            ->andWhere('user.status = :activeUserStatus')
            ->andWhere("user.email <> ''")
            ->setParameter('notificationEventCode', $notificationEventCode)
            ->setParameter('activeEventStatus', '1')
            ->setParameter('activeUserStatus', '1')
            ->orderBy('user.name', 'ASC')
            ->addOrderBy('user.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findList(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(User::class, 'user')
            ->leftJoin('user.permission', 'permission');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('user.name LIKE :search OR user.email LIKE :search OR user.userName LIKE :search OR user.phone LIKE :search OR permission.title LIKE :search OR permission.code LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(user.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->select([
                'user.id AS id',
                'user.email AS email',
                'user.userName AS username',
                'user.name AS name',
                'user.phone AS phone',
                'user.image AS image',
                'user.status AS status',
                'permission.id AS permission_id',
                'permission.title AS permission_title',
                'permission.code AS permission_code',
            ])
            ->orderBy('user.id', 'DESC')
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
