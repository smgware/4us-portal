<?php

namespace App\Repository;

use App\Entity\EmailLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class EmailLogRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailLog::class);
        $this->entityManager = $this->getEntityManager();
    }

    public function save(EmailLog $emailLog, bool $flush = true): void
    {
        $this->entityManager->persist($emailLog);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function findList(array $filters, int $page, int $itemsPerPage): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(1, $itemsPerPage);

        $qb = $this->createQueryBuilder('emailLog');
        $filterFields = [
            'email' => 'emailLog.recipientEmail',
            'name' => 'emailLog.recipientName',
            'event_name' => 'emailLog.eventName',
            'event_code' => 'emailLog.eventCode',
        ];

        foreach ($filterFields as $filterName => $fieldName) {
            $value = mb_substr(trim((string) ($filters[$filterName] ?? '')), 0, 255);
            if ($value === '') {
                continue;
            }

            $parameterName = 'filter_' . $filterName;
            $qb
                ->andWhere(sprintf('%s LIKE :%s', $fieldName, $parameterName))
                ->setParameter($parameterName, '%' . $value . '%');
        }

        $countQb = clone $qb;
        $totalRecords = (int) $countQb
            ->select('COUNT(emailLog.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));
        $page = min($page, $totalPages);

        $records = $qb
            ->select([
                'emailLog.id AS id',
                'emailLog.eventName AS event_name',
                'emailLog.eventCode AS event_code',
                'emailLog.recipientEmail AS recipient_email',
                'emailLog.recipientName AS recipient_name',
                'emailLog.datetimeAdd AS datetime_add',
            ])
            ->orderBy('emailLog.datetimeAdd', 'DESC')
            ->addOrderBy('emailLog.id', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
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
