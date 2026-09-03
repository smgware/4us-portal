<?php

namespace App\Repository;

use App\Entity\NotificationEvent;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class NotificationEventRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationEvent::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(NotificationEvent $notificationEvent, bool $flush = true): void
    {
        $this->entityManager->persist($notificationEvent);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function delete(NotificationEvent $notificationEvent, bool $flush = true): void
    {
        $this->entityManager->remove($notificationEvent);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function findList(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->entityManager->createQueryBuilder()
            ->from(NotificationEvent::class, 'notificationEvent')
            ->leftJoin(User::class, 'userOwner', 'WITH', 'userOwner.id = notificationEvent.uidAdd')
            ->leftJoin(User::class, 'userEditor', 'WITH', 'userEditor.id = notificationEvent.uidLast');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $qb
                ->andWhere('(notificationEvent.name LIKE :search OR notificationEvent.code LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if (in_array($status, ['0', '1'], true)) {
            $qb
                ->andWhere('notificationEvent.status = :status')
                ->setParameter('status', $status);
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(notificationEvent.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $records = $qb
            ->select([
                'notificationEvent.id AS id',
                'notificationEvent.name AS name',
                'notificationEvent.code AS code',
                'notificationEvent.status AS status',
                'notificationEvent.uidAdd AS uid_add',
                'notificationEvent.uidLast AS uid_last',
                'notificationEvent.datetimeAdd AS datetime_add',
                'notificationEvent.datetimeLast AS datetime_last',
                'userOwner.name AS user_owner_name',
                'userOwner.userName AS user_owner_username',
                'userEditor.name AS user_editor_name',
                'userEditor.userName AS user_editor_username',
            ])
            ->orderBy('notificationEvent.id', 'DESC')
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
